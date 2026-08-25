<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TimeEntry;
use App\Support\Period;
use Illuminate\Support\Collection;

final class EntryAdjuster
{
    private const int MINIMUM_SECONDS = 60;

    public function __construct(private readonly TimeTracker $tracker) {}

    public function blocker(Period $target, ?int $ignoreId = null): ?TimeEntry
    {
        foreach ($this->tracker->overlappingAll($target->start, $target->end, $ignoreId) as $neighbour) {
            if ($this->trim($neighbour, $target) === null) {
                return $neighbour;
            }
        }

        return null;
    }

    public function adjustments(Period $target, ?int $ignoreId = null): Collection
    {
        return $this->tracker
            ->overlappingAll($target->start, $target->end, $ignoreId)
            ->map(fn (TimeEntry $neighbour): ?TimeEntry => $this->trim($neighbour, $target))
            ->filter()
            ->values();
    }

    private function trim(TimeEntry $neighbour, Period $target): ?TimeEntry
    {
        if ($neighbour->isRunning()) {
            return null;
        }

        $startsBefore = $neighbour->started_at->lessThan($target->start);
        $endsAfter = $neighbour->ended_at->greaterThan($target->end);

        if ($startsBefore && $endsAfter) {
            return null;
        }

        if (! $startsBefore && ! $endsAfter) {
            return null;
        }

        $trimmed = $startsBefore
            ? new Period($neighbour->started_at->copy(), $target->start->copy())
            : new Period($target->end->copy(), $neighbour->ended_at->copy());

        if ($trimmed->seconds() < self::MINIMUM_SECONDS) {
            return null;
        }

        $neighbour->started_at = $trimmed->start;
        $neighbour->ended_at = $trimmed->end;

        return $neighbour;
    }
}
