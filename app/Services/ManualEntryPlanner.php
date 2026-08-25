<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Support\Period;

final class ManualEntryPlanner
{
    public function canCombine(Period $work, Period $break): bool
    {
        if (! $work->overlaps($break)) {
            return true;
        }

        return $work->contains($break) && ! $break->contains($work);
    }

    public function plan(?Period $work, ?Period $break, ?string $note): array
    {
        $periods = [];

        if ($work !== null && $break !== null && $work->overlaps($break)) {
            if ($break->start->greaterThan($work->start)) {
                $periods[] = [EntryType::Work, new Period($work->start, $break->start)];
            }

            if ($break->end->lessThan($work->end)) {
                $periods[] = [EntryType::Work, new Period($break->end, $work->end)];
            }

            $periods[] = [EntryType::Break, $break];
        } else {
            if ($work !== null) {
                $periods[] = [EntryType::Work, $work];
            }

            if ($break !== null) {
                $periods[] = [EntryType::Break, $break];
            }
        }

        usort($periods, fn (array $a, array $b): int => $a[1]->start <=> $b[1]->start);

        return $this->withNote($periods, $note);
    }

    private function withNote(array $periods, ?string $note): array
    {
        $noted = $this->firstWorkIndex($periods);

        return array_map(
            fn (array $entry, int $index): array => [
                'type' => $entry[0],
                'started_at' => $entry[1]->start,
                'ended_at' => $entry[1]->end,
                'note' => $index === $noted ? $note : null,
            ],
            $periods,
            array_keys($periods),
        );
    }

    private function firstWorkIndex(array $periods): int
    {
        foreach ($periods as $index => [$type]) {
            if ($type === EntryType::Work) {
                return $index;
            }
        }

        return 0;
    }
}
