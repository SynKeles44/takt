<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\AwayGap;
use App\Models\TimeEntry;
use Illuminate\Support\Carbon;

/**
 * The Mac was locked or asleep while a timer kept running — the most common reason for a wrong
 * day. The gap is recorded, surfaced afterwards, and only ever corrected on an explicit answer:
 * book a break, shorten the time, or leave it.
 */
final class AwayTime
{
    /** Below this a gap is noise: locking the screen to fetch a coffee is not a booking error. */
    public const int MINIMUM_SECONDS = 300;

    public function record(Carbon $from, Carbon $to): ?AwayGap
    {
        if ($from->greaterThanOrEqualTo($to) || $from->diffInSeconds($to) < self::MINIMUM_SECONDS) {
            return null;
        }

        // only worth recording if work was running while nobody was there
        $overlapping = TimeEntry::query()
            ->ofType(EntryType::Work)
            ->where('started_at', '<', $to)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $from))
            ->exists();

        if (! $overlapping) {
            return null;
        }

        $existing = AwayGap::query()
            ->open()
            ->where('started_at', '<=', $to)
            ->where('ended_at', '>=', $from)
            ->first();

        if ($existing !== null) {
            // the same absence reported twice (sleep and lock both fire) stays one gap
            $existing->update([
                'started_at' => $from->min($existing->started_at),
                'ended_at' => $to->max($existing->ended_at),
            ]);

            return $existing;
        }

        return AwayGap::query()->create(['started_at' => $from, 'ended_at' => $to]);
    }

    public function pending(): ?AwayGap
    {
        return AwayGap::query()->open()->orderByDesc('ended_at')->first();
    }

    /** Turns the gap into a break, splitting the work around it. */
    public function asBreak(AwayGap $gap, TimeTracker $tracker): void
    {
        $tracker->stop($gap->started_at);

        TimeEntry::query()->create([
            'type' => EntryType::Break,
            'started_at' => $gap->started_at,
            'ended_at' => $gap->ended_at,
        ]);

        $tracker->start(EntryType::Work, $gap->ended_at);

        $gap->update(['resolved_at' => Carbon::now()]);
    }

    /** Cuts the work at the moment the Mac went away and starts again when it came back. */
    public function shorten(AwayGap $gap, TimeTracker $tracker): void
    {
        $tracker->stop($gap->started_at);
        $tracker->start(EntryType::Work, $gap->ended_at);

        $gap->update(['resolved_at' => Carbon::now()]);
    }

    public function keep(AwayGap $gap): void
    {
        $gap->update(['resolved_at' => Carbon::now()]);
    }
}
