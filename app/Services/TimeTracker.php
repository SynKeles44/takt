<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\Ticket;
use App\Models\TimeEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TimeTracker
{
    public function running(): ?TimeEntry
    {
        return TimeEntry::query()->running()->latest('started_at')->first();
    }

    /**
     * A ticket may be given, and then the booking is evidence rather than a guess: it is what
     * turns "time per ticket" from an even split of the day into a measurement. A timer already
     * running for the same type but a different ticket is closed and reopened, because the work
     * did change — only the same type on the same ticket is left alone.
     */
    public function start(EntryType $type, ?CarbonInterface $at = null, ?Ticket $ticket = null): TimeEntry
    {
        $at = $at ? Carbon::instance($at->toDateTimeImmutable()) : Carbon::now();

        return DB::transaction(function () use ($type, $at, $ticket): TimeEntry {
            $running = $this->running();

            if ($running !== null) {
                if ($running->type === $type && $running->ticket_id === $ticket?->getKey()) {
                    return $running;
                }

                $this->close($running, $at);
            }

            return TimeEntry::query()->create([
                'type' => $type,
                'ticket_id' => $ticket?->getKey(),
                'started_at' => $at,
            ]);
        });
    }

    public function stop(?CarbonInterface $at = null): ?TimeEntry
    {
        $at = $at ? Carbon::instance($at->toDateTimeImmutable()) : Carbon::now();

        return DB::transaction(function () use ($at): ?TimeEntry {
            $running = $this->running();

            if ($running === null) {
                return null;
            }

            return $this->close($running, $at);
        });
    }

    /**
     * The shape of the last day that was actually booked: earliest start, latest end, the
     * first break, and the note if there was one. That is what "book like last time" fills in
     * — a stable rhythm is the common case, and retyping it is the friction.
     *
     * @return array<string, string>|null
     */
    public function lastPattern(?CarbonInterface $exclude = null): ?array
    {
        $skip = $exclude?->toDateString();

        $day = TimeEntry::query()
            ->ofType(EntryType::Work)
            ->completed()
            ->when($skip !== null, fn ($query) => $query->whereDate('started_at', '!=', $skip))
            ->orderByDesc('started_at')
            ->value('started_at');

        if ($day === null) {
            return null;
        }

        $entries = TimeEntry::query()
            ->onDay(Carbon::parse($day))
            ->completed()
            ->orderBy('started_at')
            ->get();

        $work = $entries->where('type', EntryType::Work);

        if ($work->isEmpty()) {
            return null;
        }

        $break = $entries->firstWhere('type', EntryType::Break);

        return array_filter([
            'date' => Carbon::parse($day)->toDateString(),
            'work_starts_at' => $work->min('started_at')->format('H:i'),
            'work_ends_at' => $work->max('ended_at')->format('H:i'),
            'break_starts_at' => $break?->started_at->format('H:i'),
            'break_ends_at' => $break?->ended_at?->format('H:i'),
            'note' => $work->first()->note,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }

    public function totalsForDay(CarbonInterface $day): array
    {
        return $this->totals(
            TimeEntry::query()->onDay($day)->get(),
        );
    }

    public function totalsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->totals(
            TimeEntry::query()->between($from, $to)->get(),
        );
    }

    public function totals(Collection $entries): array
    {
        $now = Carbon::now();

        $work = 0;
        $break = 0;

        foreach ($entries as $entry) {
            $seconds = $entry->durationInSeconds($now);

            match ($entry->type) {
                EntryType::Work => $work += $seconds,
                EntryType::Break => $break += $seconds,
            };
        }

        return [
            'work' => $work,
            'break' => $break,
            'gross' => $work + $break,
        ];
    }

    /** @param list<string> $exemptDates */
    public function balance(int $dailyTargetSeconds, ?CarbonInterface $until = null, array $exemptDates = []): array
    {
        $until = Carbon::instance(($until ?? Carbon::today())->toDateTimeImmutable())->endOfDay();

        $days = TimeEntry::query()
            ->ofType(EntryType::Work)
            ->completed()
            ->where('started_at', '<=', $until)
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        $worked = $days->sum(fn (Collection $entries): int => $this->totals($entries)['work']);

        $exemptDays = $days->filter(fn (Collection $entries, string $date): bool => in_array($date, $exemptDates, true));

        /*
         * A day without a target contributes its whole booked time as overtime — right for
         * real work on a holiday, and the single reason a balance can look inexplicable.
         * It is reported separately so the number can be traced instead of doubted.
         */
        return [
            'seconds' => $worked - ($days->count() - $exemptDays->count()) * $dailyTargetSeconds,
            'worked' => $worked,
            'days' => $days->count() - $exemptDays->count(),
            'exempt' => $exemptDays->count(),
            'exempt_worked' => $exemptDays->sum(fn (Collection $entries): int => $this->totals($entries)['work']),
        ];
    }

    public function overlapping(CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): ?TimeEntry
    {
        return $this->overlappingAll($start, $end, $ignoreId)->first();
    }

    public function overlappingAll(CarbonInterface $start, CarbonInterface $end, ?int $ignoreId = null): Collection
    {
        return TimeEntry::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('started_at', '<', $end)
            ->where(function ($query) use ($start): void {
                $query->where('ended_at', '>', $start)->orWhereNull('ended_at');
            })
            ->orderBy('started_at')
            ->get();
    }

    public function dailyBreakdown(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $entries = TimeEntry::query()
            ->between($from->copy()->startOfDay(), $to->copy()->endOfDay())
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        $days = collect();

        for ($day = $from->copy()->startOfDay(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $key = $day->toDateString();

            $days->put($key, [
                'date' => $day->copy(),
                'entries' => ($entries->get($key) ?? collect())->sortBy('started_at')->values(),
                'totals' => $this->totals($entries->get($key) ?? collect()),
            ]);
        }

        return $days;
    }

    private function close(TimeEntry $entry, CarbonInterface $at): TimeEntry
    {
        $endsAt = $at->lessThanOrEqualTo($entry->started_at)
            ? $entry->started_at->copy()->addSecond()
            : $at;

        $entry->update(['ended_at' => $endsAt]);

        return $entry->refresh();
    }
}
