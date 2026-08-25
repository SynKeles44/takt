<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceType;
use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use App\Services\CalendarFeed;
use App\Services\WorkCalendar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request, WorkCalendar $calendar): View
    {
        $request->validate([
            'monat' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = ($request->filled('monat')
            ? Carbon::createFromFormat('Y-m-d', $request->string('monat')->toString().'-01')
            : Carbon::today())->startOfMonth();

        $gridStart = $month->copy()->startOfWeek();
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek();

        $entries = TimeEntry::query()
            ->between($gridStart, $gridEnd->copy()->endOfDay())
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        $todos = Todo::query()
            ->dated()
            ->whereBetween('due_at', [$gridStart, $gridEnd->copy()->endOfDay()])
            ->with(['tags', 'steps'])
            ->get()
            ->groupBy(fn (Todo $todo): string => $todo->due_at->toDateString());

        $exemptions = $calendar->exemptions($request->user(), $gridStart, $gridEnd);

        $days = collect();

        for ($day = $gridStart->copy(); $day->lessThanOrEqualTo($gridEnd); $day->addDay()) {
            $key = $day->toDateString();
            $dayEntries = $entries->get($key) ?? collect();

            $days->push([
                'date' => $day->copy(),
                'exemption' => $exemptions[$key] ?? null,
                'inMonth' => $day->isSameMonth($month),
                'work' => (int) $dayEntries->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()),
                'todos' => $todos->get($key) ?? collect(),
            ]);
        }

        return view('calendar', [
            'month' => $month,
            'weeks' => $days->chunk(7),
            'previousMonth' => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $month->copy()->addMonth()->format('Y-m'),
            'isCurrentMonth' => $month->isSameMonth(Carbon::today()),
            'monthWork' => (int) $days->where('inMonth', true)->sum('work'),
            'dailyTarget' => $request->user()->dailyTargetSeconds(),
            'vacation' => $calendar->vacationSummary($request->user()),
            // the calendar books an absence itself, straight from a marked range
            'types' => AbsenceType::cases(),
        ]);
    }

    public function feed(string $token, CalendarFeed $feed): Response
    {
        $user = User::query()->where('ical_token', $token)->firstOrFail();

        $todos = Todo::query()
            ->withoutGlobalScope('owner')
            ->where('user_id', $user->getKey())
            ->dated()
            ->with('tags')
            ->get();

        return response($feed->build($user, $todos), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="takt.ics"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }
}
