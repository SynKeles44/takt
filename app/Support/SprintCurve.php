<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The shape of a sprint over its own days.
 *
 * Three curves, and each one is a count of things that actually happened: how many tickets had
 * been started by each day, how many had been closed, and how many closed on that day alone. The
 * scope is a flat line because Linear's API gives the scope as it stands now and not as it stood
 * on each day — their chart slopes because they keep the history, and drawing a slope from today's
 * number would be inventing one. The ideal is the only constructed line here and is drawn dashed,
 * which is what dashed means.
 *
 * Built once and read twice: the sprint page and the board's side panel draw the same chart, and
 * two implementations of one curve would drift the first time either was corrected.
 */
final class SprintCurve
{
    /**
     * @param  list<array{started_at: ?Carbon, completed_at: ?Carbon}>  $tickets
     * @return list<array<string, mixed>>
     */
    public static function days(Carbon $from, Carbon $to, array $tickets, array $secondsPerDay = []): array
    {
        $scope = count($tickets);
        $length = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()));

        $startedOn = self::countPerDay($tickets, 'started_at');
        $closedOn = self::countPerDay($tickets, 'completed_at');

        $days = [];
        $started = 0;
        $closed = 0;

        for ($day = $from->copy()->startOfDay(), $i = 0; $day->lte($to); $day->addDay(), $i++) {
            $key = $day->toDateString();

            $started += $startedOn[$key] ?? 0;
            $closed += $closedOn[$key] ?? 0;

            $days[] = [
                'date' => $day->copy(),
                'scope' => $scope,
                // a curve stops at today: the rest of the sprint has not happened yet
                'started' => $day->isFuture() ? null : $started,
                'done' => $day->isFuture() ? null : $closed,
                'closed' => $closedOn[$key] ?? 0,
                'ideal' => round($scope * ($i / $length), 2),
                'seconds' => $secondsPerDay[$key] ?? 0,
                'future' => $day->isFuture(),
                'weekend' => $day->isWeekend(),
            ];
        }

        return $days;
    }

    /**
     * @param  list<array{started_at: ?Carbon, completed_at: ?Carbon}>  $tickets
     * @return array<string, int>
     */
    private static function countPerDay(array $tickets, string $field): array
    {
        $counts = [];

        foreach ($tickets as $ticket) {
            $at = $ticket[$field] ?? null;

            if ($at instanceof Carbon) {
                $key = $at->toDateString();
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
