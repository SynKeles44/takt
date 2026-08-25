<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\DueState;
use App\Models\DayNote;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Services\TimeTracker;
use App\Services\TodoMaintenance;
use App\Services\WorkCalendar;
use App\Services\WorkTimeCompliance;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        TimeTracker $tracker,
        TodoMaintenance $maintenance,
        WorkCalendar $calendar,
        WorkTimeCompliance $compliance,
    ): View {
        $request->validate([
            'woche' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $maintenance->run();

        $user = $request->user();
        $today = Carbon::today();

        $chartStart = ($request->filled('woche')
            ? Carbon::createFromFormat('Y-m-d', $request->string('woche')->toString())
            : $today->copy())->startOfWeek();

        $chartEnd = $chartStart->copy()->addDays(6);
        $currentWeekStart = $today->copy()->startOfWeek();

        $todayEntries = TimeEntry::query()->onDay($today)->orderByDesc('started_at')->get();
        $exemptions = $calendar->exemptions($user, $today, $today);
        $previousEntry = TimeEntry::query()
            ->completed()
            ->where('started_at', '<', $today)
            ->orderByDesc('ended_at')
            ->first();

        return view('dashboard', [
            'running' => $tracker->running(),
            'balance' => $tracker->balance($user->dailyTargetSeconds(), null, $calendar->exemptDatesForBalance($user)),
            'exemption' => $exemptions[$today->toDateString()] ?? null,
            'hints' => $compliance->check($todayEntries, $previousEntry),
            'dayNote' => DayNote::query()->whereDate('day', $today->toDateString())->first(),
            'dailyTarget' => $user->dailyTargetSeconds(),
            'weeklyTarget' => $user->weeklyTargetSeconds(),
            'today' => $today,
            'todayTotals' => $tracker->totalsForDay($today),
            'entries' => $todayEntries,
            'weekTotals' => $tracker->totalsBetween($currentWeekStart, $currentWeekStart->copy()->addDays(6)->endOfDay()),
            'week' => $tracker->dailyBreakdown($chartStart, $chartEnd),
            'chartStart' => $chartStart,
            'chartEnd' => $chartEnd,
            'chartTotals' => $tracker->totalsBetween($chartStart, $chartEnd->copy()->endOfDay()),
            'previousWeek' => $chartStart->copy()->subWeek()->toDateString(),
            'nextWeek' => $chartStart->copy()->addWeek()->toDateString(),
            'isCurrentWeek' => $chartStart->isSameWeek($today),
            'todos' => $this->todos(),
            'openTodos' => Todo::query()->open()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** @return Collection<int, Todo> */
    private function todos(): Collection
    {
        $order = array_flip(array_map(
            fn (DueState $state): string => $state->value,
            DueState::groups(),
        ));

        return Todo::query()
            ->open()
            ->with(['tags', 'steps'])
            ->inOrder()
            ->get()
            ->sortBy(fn (Todo $todo): string => sprintf(
                '%02d-%s',
                $order[$todo->dueState()->value] ?? 99,
                $todo->due_at?->toDateTimeString() ?? '9999-12-31 23:59:59',
            ))
            ->values();
    }
}
