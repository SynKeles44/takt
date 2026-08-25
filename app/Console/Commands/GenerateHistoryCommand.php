<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use App\Services\WorkHistoryGenerator;
use App\Support\Duration;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

class GenerateHistoryCommand extends Command
{
    protected $signature = 'takt:history
        {--months=4 : How many months back to fill, aligned to full ISO weeks}
        {--skip-weeks=2 : How many calendar weeks stay empty, including the current one}
        {--from= : Explicit first day of the range (Y-m-d), overrides --months}
        {--to= : Explicit last day of the range (Y-m-d), overrides --skip-weeks}
        {--keep : Only clear the generated range, leave later entries untouched}
        {--balance=1 : Target plus/minus balance in hours}
        {--user= : Email of the user the entries belong to}
        {--seed= : Seed for reproducible output}
        {--force : Overwrite existing entries in the range without asking}';

    protected $description = 'Fill the history with realistic random work days that add up to the weekly target';

    public function handle(TimeTracker $tracker): int
    {
        $user = $this->resolveUser();

        if ($user === null) {
            return self::FAILURE;
        }

        Auth::setUser($user);

        $skipWeeks = (int) $this->option('skip-weeks');

        $from = $this->date('from') ?? Carbon::today()->subMonths((int) $this->option('months'))->startOfWeek();

        $to = $this->date('to') ?? ($skipWeeks < 1
            ? Carbon::today()
            : Carbon::today()->startOfWeek()->subWeeks($skipWeeks - 1)->subDay());

        if ($from === false || $to === false) {
            $this->components->error('--from and --to expect a date as Y-m-d.');

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->components->error('The range is empty — check --from/--to, --skip-weeks and --months.');

            return self::FAILURE;
        }

        $clearTo = $this->option('keep') ? $to->copy()->endOfDay() : Carbon::today()->endOfDay();

        if ($clearTo->lessThan($to)) {
            $clearTo = $to->copy()->endOfDay();
        }

        $existing = TimeEntry::query()->between($from, $clearTo)->count();

        if ($existing > 0 && ! $this->option('force') && ! $this->components->confirm(
            sprintf(
                '%d existing entries between %s and %s will be deleted. Continue?',
                $existing,
                $from->toDateString(),
                $clearTo->toDateString(),
            ),
        )) {
            return self::FAILURE;
        }

        $dailyTarget = $user->dailyTargetSeconds();
        $seed = $this->option('seed');

        $generator = new WorkHistoryGenerator(
            $seed === null ? new Randomizer : new Randomizer(new Mt19937((int) $seed)),
            $dailyTarget,
            app(WorkCalendar::class)->exemptDatesForBalance($user, $to),
        );

        $blocks = $generator->generate($from, $to, (int) round((float) $this->option('balance') * 3600));

        TimeEntry::query()->between($from, $clearTo)->delete();
        $blocks->chunk(200)->each(fn ($chunk) => TimeEntry::query()->insert(
            $chunk->map(fn (array $row): array => $row + ['user_id' => $user->getKey()])->all(),
        ));

        $this->newLine();
        $this->components->info(sprintf(
            '%d entries written for %s – %s, empty from %s on.',
            $blocks->count(),
            $from->toDateString(),
            $to->toDateString(),
            $to->copy()->addDay()->toDateString(),
        ));

        $this->weeklyTable($tracker, $from, $to);

        $balance = $tracker->balance($dailyTarget);

        $this->components->twoColumnDetail(
            '<fg=white;options=bold>Plus/minus balance</>',
            sprintf('<fg=white;options=bold>%s</> over %d booked days', Duration::signed($balance['seconds']), $balance['days']),
        );
        $this->newLine();

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $email = $this->option('user');

        if ($email !== null) {
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $this->components->error(sprintf('No user with email %s.', $email));
            }

            return $user;
        }

        $users = User::query()->orderBy('id')->get();

        if ($users->count() === 1) {
            return $users->first();
        }

        $this->components->error($users->isEmpty()
            ? 'No user exists yet — register one first.'
            : 'Several users exist — pass --user=email.');

        return null;
    }

    private function date(string $option): Carbon|false|null
    {
        $value = $this->option($option);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $value)->startOfDay();
        } catch (Throwable) {
            return false;
        }
    }

    private function weeklyTable(TimeTracker $tracker, Carbon $from, Carbon $to): void
    {
        $rows = TimeEntry::query()
            ->ofType(EntryType::Work)
            ->between($from, $to->copy()->endOfDay())
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->isoFormat('GGGG-[W]WW'))
            ->map(fn ($entries, string $week): array => [
                $week,
                $entries->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString())->count(),
                Duration::human($tracker->totals($entries)['work']),
                Duration::decimalHours($tracker->totals($entries)['work']).' h',
            ])
            ->values()
            ->all();

        $this->table(['Week', 'Days', 'Work', 'Decimal'], $rows);
    }
}
