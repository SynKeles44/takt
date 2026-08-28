<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DueState;
use App\Enums\Widget;
use App\Models\DashboardWidget;
use App\Models\DayNote;
use App\Models\Project;
use App\Models\Snippet;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The dashboard is a list of widgets the user picks. This service answers two
 * questions: which widgets does this dashboard show, and what does each one need.
 * Data is only loaded for widgets that are actually on the page.
 */
final class Dashboard
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        private readonly TimeTracker $tracker,
        private readonly WorkCalendar $calendar,
        private readonly WorkTimeCompliance $compliance,
        private readonly Insights $insights,
        private readonly Commits $commits,
        private readonly Reviews $reviews,
        private readonly ProjectRunner $runner,
    ) {}

    /**
     * The board as it stands. Only a user who never arranged anything gets the default set —
     * once they have, an empty board stays empty, because that was their decision.
     *
     * @return Collection<int, DashboardWidget>
     */
    public function layout(User $user): Collection
    {
        if ($user->dashboard_arranged) {
            return DashboardWidget::query()->inOrder()->get();
        }

        return collect(Widget::defaults())->map(fn (Widget $widget, int $position): DashboardWidget => new DashboardWidget([
            'widget' => $widget,
            'position' => $position,
        ]));
    }

    /** Back to the default set, still marked as arranged so it can be edited from there. */
    public function reset(User $user): void
    {
        DashboardWidget::query()->delete();

        foreach (Widget::defaults() as $position => $widget) {
            DashboardWidget::query()->create(['widget' => $widget, 'position' => $position]);
        }

        $user->forceFill(['dashboard_arranged' => true])->save();
    }

    /**
     * @return array<string, mixed> everything the widget's view needs
     */
    public function data(Widget $widget, User $user, ?string $week = null): array
    {
        return match ($widget) {
            Widget::Timer => [
                'running' => $this->running(),
                'exemption' => $this->calendar->exemptions($user, $this->today(), $this->today())[$this->today()->toDateString()] ?? null,
                'hints' => $this->compliance->check($this->todayEntries(), $this->previousEntry()),
            ],
            Widget::Stats => [
                'running' => $this->running(),
                'today' => $this->today(),
                'todayTotals' => $this->todayTotals(),
                'weekTotals' => $this->tracker->totalsBetween($this->weekStart(), $this->weekStart()->copy()->addDays(6)->endOfDay()),
                'dailyTarget' => $user->dailyTargetSeconds(),
                'weeklyTarget' => $user->weeklyTargetSeconds(),
                'balance' => $this->tracker->balance($user->dailyTargetSeconds(), null, $this->calendar->exemptDatesForBalance($user)),
            ],
            Widget::WeekChart => $this->weekChart($user, $week),
            Widget::Entries => ['entries' => $this->todayEntries()],
            Widget::Booking => [
                'openTodos' => Todo::query()->open()->orderBy('title')->get(['id', 'title']),
                'pattern' => $this->tracker->lastPattern($this->today()),
            ],
            Widget::Note => [
                'today' => $this->today(),
                'dayNote' => DayNote::query()->whereDate('day', $this->today()->toDateString())->first(),
            ],
            Widget::MonthSummary => $this->monthSummary($user),
            Widget::WeekBalance => $this->weekBalance($user),
            Widget::YearHeatmap => [
                'year' => $this->insights->build($user, 'jahr'),
                'dailyTarget' => $user->dailyTargetSeconds(),
            ],
            Widget::Absences => ['absences' => $this->absences($user)],
            Widget::HomeOffice => $this->homeOffice($user),
            Widget::Todos => ['todos' => $this->todos()],
            Widget::TodoTags => ['tags' => $this->todoTags()],
            Widget::TodoProgress => $this->todoProgress(),
            Widget::CommitsToday => [
                'groups' => $groups = $this->commits->forDay($this->today(), $this->projects()),
                'count' => $this->commits->count($groups),
                'today' => $this->today(),
            ],
            Widget::CommitsWeek => [
                'days' => $this->commits->perDay($this->weekStart(), $this->today(), $this->projects()),
            ],
            Widget::ReviewQueue, Widget::MyPullRequests => [
                'reviews' => $this->reviews->forUser($user),
                'configured' => $this->reviews->configured($user),
            ],
            Widget::ProjectLauncher => [
                'projects' => $this->projects(),
                'states' => $this->projects()->mapWithKeys(fn (Project $project): array => [
                    $project->getKey() => $this->runner->state($project),
                ]),
            ],
            Widget::Snippets => ['snippets' => Snippet::query()->inOrder()->take(6)->get()],
            Widget::TestPost => [],
            Widget::DevLinks => ['projects' => $this->projects()->filter(fn (Project $project): bool => $project->repository !== null)],
        };
    }

    // MARK: shared pieces, each read once per request

    private function today(): Carbon
    {
        return $this->memo['today'] ??= Carbon::today();
    }

    private function weekStart(): Carbon
    {
        return ($this->memo['weekStart'] ??= $this->today()->copy()->startOfWeek())->copy();
    }

    private function running(): ?TimeEntry
    {
        return $this->memo['running'] ??= $this->tracker->running();
    }

    /** @return array{work: int, break: int, gross: int} */
    private function todayTotals(): array
    {
        return $this->memo['todayTotals'] ??= $this->tracker->totalsForDay($this->today());
    }

    /** @return Collection<int, TimeEntry> */
    private function todayEntries(): Collection
    {
        return $this->memo['todayEntries'] ??= TimeEntry::query()->onDay($this->today())->orderByDesc('started_at')->get();
    }

    private function previousEntry(): ?TimeEntry
    {
        return $this->memo['previousEntry'] ??= TimeEntry::query()
            ->completed()
            ->where('started_at', '<', $this->today())
            ->orderByDesc('ended_at')
            ->first();
    }

    /** @return Collection<int, Project> */
    private function projects(): Collection
    {
        return $this->memo['projects'] ??= Project::query()->inOrder()->get();
    }

    /** @return array<string, mixed> */
    private function weekChart(User $user, ?string $week): array
    {
        $start = ($week !== null ? Carbon::createFromFormat('Y-m-d', $week) : $this->today()->copy())->startOfWeek();
        $end = $start->copy()->addDays(6);

        return [
            'week' => $this->tracker->dailyBreakdown($start, $end),
            'chartStart' => $start,
            'chartEnd' => $end,
            'chartTotals' => $this->tracker->totalsBetween($start, $end->copy()->endOfDay()),
            'previousWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'isCurrentWeek' => $start->isSameWeek($this->today()),
            'dailyTarget' => $user->dailyTargetSeconds(),
        ];
    }

    /** @return array<string, mixed> */
    private function monthSummary(User $user): array
    {
        $start = $this->today()->copy()->startOfMonth();
        $totals = $this->tracker->totalsBetween($start, $this->today()->copy()->endOfDay());

        $booked = $this->tracker->dailyBreakdown($start, $this->today())
            ->filter(fn (array $day): bool => $day['totals']['work'] > 0);

        return [
            'month' => $start,
            'totals' => $totals,
            'days' => $booked->count(),
            'average' => $booked->count() > 0 ? (int) round($totals['work'] / $booked->count()) : 0,
            'target' => $user->dailyTargetSeconds() * $booked->count(),
        ];
    }

    /** @return array<string, mixed> Work per week against the target, for the last six weeks. */
    private function weekBalance(User $user): array
    {
        $weeks = collect();
        $target = $user->weeklyTargetSeconds();

        for ($offset = 5; $offset >= 0; $offset--) {
            $start = $this->weekStart()->subWeeks($offset);
            $end = $start->copy()->addDays(6)->endOfDay();

            $weeks->push([
                'start' => $start,
                'work' => $this->tracker->totalsBetween($start, $end)['work'],
                'current' => $offset === 0,
            ]);
        }

        return ['weeks' => $weeks, 'target' => $target];
    }

    /** @return array<string, mixed> */
    private function homeOffice(User $user): array
    {
        $windows = [7, 30, 365];
        $window = in_array($user->home_office_window, $windows, true) ? $user->home_office_window : 30;

        return [
            'summary' => $this->calendar->homeOfficeSummary($user, $window),
            'thisWeek' => count($this->calendar->homeOfficeDates(
                $this->weekStart(),
                $this->weekStart()->copy()->addDays(6),
            )),
            'windows' => $windows,
            'window' => $window,
        ];
    }

    /** @return Collection<int, array{date: Carbon, label: string, tone: string}> */
    private function absences(User $user): Collection
    {
        $from = $this->today();
        $to = $this->today()->copy()->addDays(60);

        return collect($this->calendar->exemptions($user, $from, $to))
            ->map(fn (array $exemption, string $date): array => [
                'date' => Carbon::parse($date),
                'label' => $exemption['label'],
                'tone' => $exemption['tone'],
            ])
            ->sortBy(fn (array $entry): string => $entry['date']->toDateString())
            ->values()
            ->take(6);
    }

    /** @return Collection<int, Todo> */
    private function todos(): Collection
    {
        if (isset($this->memo['todos'])) {
            return $this->memo['todos'];
        }

        $order = array_flip(array_map(fn (DueState $state): string => $state->value, DueState::groups()));

        return $this->memo['todos'] = Todo::query()
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

    /** @return Collection<int, array{label: string, classes: string, count: int}> */
    private function todoTags(): Collection
    {
        return $this->todos()
            ->flatMap(fn (Todo $todo): Collection => $todo->tags)
            ->groupBy(fn ($tag): int => $tag->getKey())
            ->map(fn (Collection $group): array => [
                'label' => $group->first()->name,
                'classes' => $group->first()->color->classes(),
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(8);
    }

    /** @return array<string, mixed> */
    private function todoProgress(): array
    {
        $open = $this->todos();

        return [
            'open' => $open->count(),
            'urgent' => $open->filter(fn (Todo $todo): bool => $todo->dueState()->isUrgent())->count(),
            'doneThisWeek' => Todo::query()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', $this->weekStart())
                ->count(),
            'steps' => [
                'done' => $open->sum(fn (Todo $todo): int => $todo->steps->filter(fn ($step): bool => $step->isDone())->count()),
                'total' => $open->sum(fn (Todo $todo): int => $todo->steps->count()),
            ],
        ];
    }
}
