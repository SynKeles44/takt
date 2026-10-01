<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\Absence;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Filling several days at once, with the days not looking identical.
 *
 * The scatter is the point. A month of entries that all read 09:00–17:00 is visibly typed in
 * rather than worked, and it is also simply false — nobody starts at the same minute every day.
 * So each day gets its own offset within a range you set, and the two ends of a stretch move
 * independently, because arriving eight minutes late does not make you leave eight minutes late.
 *
 * The break is the exception and keeps its exact times. Scattering it would shorten it on roughly
 * half the days, and the statutory minimum does not care that the shortening was random.
 */
final class BulkEntryPlanner
{
    /** Days that already carry a booking are never touched. */
    public const string SKIP_BOOKED = 'booked';

    /** Days the work calendar exempts — holidays, leave, sickness. */
    public const string SKIP_EXEMPT = 'exempt';

    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @param  list<string>  $days  Y-m-d
     * @return array{entries: list<array<string, mixed>>, skipped: array<string, list<string>>}
     */
    public function plan(
        array $days,
        string $workFrom,
        string $workTo,
        ?string $breakFrom,
        ?string $breakTo,
        int $scatterMinutes,
        bool $skipBooked = true,
        bool $skipExempt = true,
    ): array {
        $entries = [];
        $skipped = [self::SKIP_BOOKED => [], self::SKIP_EXEMPT => []];

        $booked = $skipBooked ? $this->bookedDays($days) : collect();
        $exempt = $skipExempt ? $this->exemptDays($days) : collect();

        foreach ($days as $day) {
            $date = Carbon::parse($day)->startOfDay();

            if ($booked->contains($date->toDateString())) {
                $skipped[self::SKIP_BOOKED][] = $date->toDateString();

                continue;
            }

            if ($exempt->contains($date->toDateString())) {
                $skipped[self::SKIP_EXEMPT][] = $date->toDateString();

                continue;
            }

            $work = $this->stretch($date, $workFrom, $workTo, $scatterMinutes);

            if ($work === null) {
                continue;
            }

            [$workStart, $workEnd] = $work;

            if ($breakFrom !== null && $breakTo !== null) {
                /*
                 * The break is NOT scattered, and that is a legal point rather than a preference:
                 * over six hours of work the statutory break is thirty minutes, so a scatter that
                 * shortens it manufactures a compliance warning on days that were entered as
                 * compliant. Both ends stay where they were typed; only the working time varies.
                 */
                $break = $this->stretch($date, $breakFrom, $breakTo, 0);

                /*
                 * A break inside the working time splits it in two, which is what the rest of the
                 * app expects — overlapping entries would each count in full and inflate the day.
                 */
                if ($break !== null && $break[0] > $workStart && $break[1] < $workEnd) {
                    [$breakStart, $breakEnd] = $break;

                    $entries[] = $this->entry(EntryType::Work, $workStart, $breakStart);
                    $entries[] = $this->entry(EntryType::Break, $breakStart, $breakEnd);
                    $entries[] = $this->entry(EntryType::Work, $breakEnd, $workEnd);

                    continue;
                }
            }

            $entries[] = $this->entry(EntryType::Work, $workStart, $workEnd);
        }

        return ['entries' => $entries, 'skipped' => $skipped];
    }

    /**
     * One stretch on one day, with both ends moved independently.
     *
     * @return array{Carbon, Carbon}|null
     */
    private function stretch(Carbon $day, string $from, string $to, int $scatter): ?array
    {
        $start = $this->at($day, $from, $scatter);
        $end = $this->at($day, $to, $scatter);

        // a scatter wide enough to invert the stretch produces nothing rather than a negative day
        return $end->greaterThan($start) ? [$start, $end] : null;
    }

    private function at(Carbon $day, string $time, int $scatter): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        $at = $day->copy()->setTime($hour, $minute);

        return $scatter > 0 ? $at->addMinutes(random_int(-$scatter, $scatter)) : $at;
    }

    /** @return array<string, mixed> */
    private function entry(EntryType $type, Carbon $start, Carbon $end): array
    {
        return [
            'type' => $type,
            'started_at' => $start,
            'ended_at' => $end,
        ];
    }

    /**
     * @param  list<string>  $days
     * @return Collection<int, string>
     */
    private function bookedDays(array $days): Collection
    {
        if ($days === []) {
            return collect();
        }

        $from = Carbon::parse(min($days))->startOfDay();
        $to = Carbon::parse(max($days))->endOfDay();

        return TimeEntry::query()
            ->between($from, $to)
            ->get()
            ->map(fn (TimeEntry $entry): string => $entry->started_at->toDateString())
            ->unique()
            ->values();
    }

    /**
     * @param  list<string>  $days
     * @return Collection<int, string>
     */
    private function exemptDays(array $days): Collection
    {
        if ($days === []) {
            return collect();
        }

        $from = Carbon::parse(min($days))->startOfDay();
        $to = Carbon::parse(max($days))->endOfDay();

        $exempt = collect($this->calendar->exemptDates(auth()->user(), $from, $to));

        // a weekend is exempt by nature, and the calendar does not list it as an absence
        return collect($days)
            ->filter(function (string $day) use ($exempt): bool {
                $date = Carbon::parse($day);

                return $exempt->contains($date->toDateString()) || $date->isWeekend();
            })
            ->values();
    }
}
