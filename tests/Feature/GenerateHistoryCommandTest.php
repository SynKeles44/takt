<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class GenerateHistoryCommandTest extends TestCase
{
    use RefreshDatabase;

    private const int DAILY_TARGET = 28_800;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->login();

        Carbon::setTestNow('2026-08-20 14:00:00');
    }

    private function generate(int $seed, float $balance = 1): Collection
    {
        $this->artisan('takt:history', [
            '--months' => 3,
            '--skip-weeks' => 2,
            '--balance' => $balance,
            '--seed' => $seed,
            '--force' => true,
        ])->assertSuccessful();

        return TimeEntry::query()->orderBy('started_at')->get();
    }

    public function test_the_balance_lands_exactly_on_the_requested_hours(): void
    {
        $this->generate(1);

        $balance = app(TimeTracker::class)->balance(28_800);

        $this->assertSame(3_600, $balance['seconds']);
        $this->assertGreaterThan(50, $balance['days']);
    }

    public function test_every_week_hits_the_target_of_the_days_it_covers(): void
    {
        $entries = $this->generate(2)->where('type', EntryType::Work);
        $weeks = $entries->groupBy(fn (TimeEntry $entry): string => $entry->started_at->isoFormat('GGGG-WW'));

        $exempt = app(WorkCalendar::class)->exemptDatesForBalance($this->user);
        $overtimeWeeks = 0;

        foreach ($weeks as $week => $group) {
            $days = $group->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());
            $worked = $group->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

            // a week with a public holiday covers fewer days, and its target shrinks with it
            $workdays = $this->workdaysOf($group->first()->started_at, $exempt);

            $this->assertSame($workdays, $days->count(), "week {$week} should cover its workdays");
            $this->assertContains($worked, [$days->count() * self::DAILY_TARGET, $days->count() * self::DAILY_TARGET + 3_600], "week {$week} is off target");

            // and no day of it is a holiday: work on one is overtime and would skew the balance
            foreach ($days->keys() as $date) {
                $this->assertNotContains($date, $exempt, "{$date} is exempt and must stay empty");
            }

            $overtimeWeeks += $worked > $days->count() * self::DAILY_TARGET ? 1 : 0;
        }

        $this->assertSame(1, $overtimeWeeks);
    }

    /** @param  list<string>  $exempt */
    private function workdaysOf(Carbon $anyDayOfWeek, array $exempt): int
    {
        $day = $anyDayOfWeek->copy()->startOfWeek();
        $count = 0;

        foreach (range(1, 5) as $ignored) {
            if (! in_array($day->toDateString(), $exempt, true)) {
                $count++;
            }

            $day->addDay();
        }

        return $count;
    }

    public function test_the_balance_is_not_pushed_up_by_public_holidays(): void
    {
        // Ascension Day and Whit Monday fall into this range; work on either is overtime,
        // which used to leave a generated history permanently in the plus
        Carbon::setTestNow('2026-06-10 14:00:00');

        $this->artisan('takt:history', ['--months' => 3, '--balance' => 0, '--seed' => 7, '--force' => true])
            ->assertSuccessful();

        $balance = app(TimeTracker::class)->balance(
            self::DAILY_TARGET,
            null,
            app(WorkCalendar::class)->exemptDatesForBalance($this->user),
        );

        $this->assertSame(0, $balance['seconds']);
    }

    public function test_the_skipped_weeks_and_weekends_stay_empty(): void
    {
        $entries = $this->generate(3);

        $firstEmptyDay = Carbon::today()->startOfWeek()->subWeek();

        $this->assertTrue($entries->every(fn (TimeEntry $entry): bool => $entry->started_at->lessThan($firstEmptyDay)));
        $this->assertTrue($entries->every(fn (TimeEntry $entry): bool => ! $entry->started_at->isWeekend()));
    }

    public function test_every_day_respects_the_start_and_end_windows(): void
    {
        $days = $this->generate(4)->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        foreach ($days as $date => $entries) {
            $start = $entries->first()->started_at;
            $end = $entries->last()->ended_at;
            $work = $entries->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());
            $break = $entries->where('type', EntryType::Break)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

            $this->assertGreaterThanOrEqual('08:00', $start->format('H:i'), "start too early on {$date}");
            $this->assertLessThanOrEqual('09:30', $start->format('H:i'), "start too late on {$date}");
            $this->assertGreaterThanOrEqual('16:00', $end->format('H:i'), "end too early on {$date}");
            $this->assertLessThanOrEqual('19:00', $end->format('H:i'), "end too late on {$date}");
            $this->assertGreaterThanOrEqual(22_500, $work, "workday too short on {$date}");
            $this->assertLessThanOrEqual(35_100, $work, "workday too long on {$date}");
            $this->assertGreaterThanOrEqual(1_800, $break, "break too short on {$date}");
            $this->assertLessThanOrEqual(5_400, $break, "break too long on {$date}");
            $this->assertSame((int) $start->diffInSeconds($end), $work + $break, "gaps or overlaps on {$date}");
        }
    }

    public function test_the_generated_days_vary(): void
    {
        $days = $this->generate(5)->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        $starts = $days->map(fn (Collection $entries): string => $entries->first()->started_at->format('H:i'))->unique();
        $lengths = $days->map(fn (Collection $entries): int => $entries->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()))->unique();
        $breaks = $days->map(fn (Collection $entries): int => $entries->where('type', EntryType::Break)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()))->unique();

        $this->assertGreaterThan(8, $starts->count());
        $this->assertGreaterThan(15, $lengths->count());
        $this->assertGreaterThan(5, $breaks->count());
    }

    public function test_entries_before_the_range_are_kept(): void
    {
        $keeper = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2025-11-04 09:00:00',
            'ended_at' => '2025-11-04 17:00:00',
        ]);

        $this->generate(6);

        $this->assertNotNull(TimeEntry::query()->find($keeper->getKey()));
    }

    public function test_entries_inside_the_skipped_weeks_are_cleared(): void
    {
        $stale = TimeEntry::query()->create([
            'type' => EntryType::Work,
            'started_at' => '2026-08-19 09:00:00',
            'ended_at' => '2026-08-19 17:00:00',
        ]);

        $this->generate(7);

        $this->assertNull(TimeEntry::query()->find($stale->getKey()));
    }

    public function test_an_explicit_range_with_keep_fills_only_that_island(): void
    {
        $this->generate(8, balance: 0);

        $before = TimeEntry::query()->count();

        $this->artisan('takt:history', [
            '--from' => '2026-08-17',
            '--to' => '2026-08-19',
            '--balance' => 1,
            '--keep' => true,
            '--seed' => 9,
            '--force' => true,
        ])->assertSuccessful();

        $island = TimeEntry::query()
            ->where('started_at', '>=', '2026-08-17 00:00:00')
            ->orderBy('started_at')
            ->get();

        $this->assertGreaterThan($before, TimeEntry::query()->count());
        $this->assertSame(
            ['2026-08-17', '2026-08-18', '2026-08-19'],
            $island->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString())->keys()->all(),
        );

        $worked = $island->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

        $this->assertSame(3 * self::DAILY_TARGET + 3_600, $worked);
        $this->assertSame(3_600, app(TimeTracker::class)->balance(28_800)['seconds']);
    }

    public function test_an_invalid_date_option_is_rejected(): void
    {
        $this->artisan('takt:history', ['--from' => 'gestern', '--force' => true])->assertFailed();
    }
}
