<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DayNote;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use App\Services\WorkTimeCompliance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HistoryController extends Controller
{
    public function __invoke(Request $request, TimeTracker $tracker, WorkCalendar $calendar, WorkTimeCompliance $compliance): View
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $weekStart = ($request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->string('from')->toString())
            : Carbon::today())->startOfWeek();

        $weekEnd = $weekStart->copy()->addDays(6);

        $days = $tracker->dailyBreakdown($weekStart, $weekEnd);
        $exemptions = $calendar->exemptions($request->user(), $weekStart, $weekEnd);

        $hints = $days->mapWithKeys(fn (array $day, string $date): array => [
            $date => $compliance->check($day['entries']),
        ]);

        $notes = DayNote::query()
            ->whereBetween('day', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get()
            ->keyBy(fn ($note): string => $note->day->toDateString());

        return view('history', [
            'dailyTarget' => $request->user()->dailyTargetSeconds(),
            'exemptions' => $exemptions,
            'hints' => $hints,
            'notes' => $notes,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days
                ->reject(fn (array $day, string $date): bool => $day['date']->isFuture()
                    && $day['totals']['gross'] === 0
                    && ! isset($exemptions[$date]))
                ->reverse(),
            'weekTotals' => $tracker->totalsBetween($weekStart, $weekEnd->copy()->endOfDay()),
            'previousWeek' => $weekStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $weekStart->copy()->addWeek()->toDateString(),
            'isCurrentWeek' => $weekStart->isSameWeek(Carbon::today()),
        ]);
    }
}
