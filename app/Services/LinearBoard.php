<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The board as Linear draws it: one column per workflow state, grouped by project inside.
 *
 * This is deliberately a second board rather than a replacement. The other one has five columns
 * that describe a day — today, next, waiting, parked, done — and they are mine; these columns are
 * the team's, and moving a card between them writes to Linear. Both answer a real question and
 * they are not the same question, so neither one is the other's settings screen.
 *
 * The columns are not configured anywhere. They are the states the current tickets actually carry,
 * ordered the way Linear orders a workflow — backlog, unstarted, started, completed, cancelled —
 * which means a board spanning two teams with different workflows still comes out in an order a
 * person reads top to bottom, without this app having to know either workflow.
 */
final class LinearBoard
{
    /** Linear's own ordering of state types; anything unknown sorts after all of them. */
    private const array TYPE_ORDER = ['local', 'triage', 'backlog', 'unstarted', 'started', 'completed', 'canceled'];

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, array{id: string, type: string}>>  $states  team => name => state
     * @return list<array<string, mixed>>
     */
    public function columns(Collection $rows, array $states, ?array $visible = null): array
    {
        $seen = [];

        /*
         * Every state of every team on the board, whether or not a ticket sits in it right now.
         *
         * An empty column is information: it says this is where things go next, and a board that
         * only draws the states it happens to be occupying rearranges itself as you work. Which of
         * them to show is a decision, and it is the user's — `$visible` carries it, null means all.
         */
        foreach ($states as $team => $workflow) {
            foreach ($workflow as $name => $state) {
                $seen[$name] ??= [
                    'name' => $name,
                    'type' => (string) ($state['type'] ?? ''),
                    'position' => (float) ($state['position'] ?? 0),
                ];
            }
        }

        foreach ($rows as $row) {
            $name = ($row['state'] ?? null) ?: null;

            /*
             * A ticket that lives only here has no Linear state, and a board that drops it would
             * be a board that loses tickets. It gets a column of its own, before the workflow —
             * which is also where it belongs: it is the one state Linear has no name for.
             */
            $seen[$name ?? ''] ??= [
                'name' => $name,
                'type' => $name === null ? 'local' : (string) ($row['state_type'] ?? ''),
                'position' => $name === null ? 0 : $this->position($name, $row['id'], $states),
            ];
        }

        if ($visible !== null) {
            $seen = array_filter(
                $seen,
                static fn (array $column): bool => in_array($column['name'] ?? '', $visible, true),
            );
        }

        usort($seen, fn (array $a, array $b): int => [$this->rank($a['type']), $a['position'], (string) $a['name']]
            <=> [$this->rank($b['type']), $b['position'], (string) $b['name']]);

        $seen = array_filter(
            $seen,
            fn (array $column): bool => $column['name'] !== null
                || $rows->contains(fn (array $row): bool => (($row['state'] ?? null) ?: null) === null),
        );

        return array_map(function (array $column) use ($rows): array {
            $mine = $rows->filter(fn (array $row): bool => (($row['state'] ?? null) ?: null) === $column['name']);

            return [
                ...$column,
                'label' => $column['name'] ?? __('app.ticket.no_state'),
                'groups' => $this->groups($mine),
                'count' => $mine->count(),
            ];
        }, array_values($seen));
    }

    /**
     * Inside a column, by project, with the ones that have none last.
     *
     * Linear sorts its groups by project name and keeps "No project" at the bottom, which is the
     * right way round: a project is a place you are looking for, and the leftovers are not.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<array{project: ?string, tickets: list<array<string, mixed>>}>
     */
    private function groups(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row): string => (string) ($row['project'] ?? ''))
            ->sortKeys()
            ->sortBy(fn (Collection $group, string $project): int => $project === '' ? 1 : 0)
            ->map(fn (Collection $group, string $project): array => [
                'project' => $project === '' ? null : $project,
                'tickets' => $group
                    ->sortByDesc(fn (array $row): string => $row['last']->toIso8601String())
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * The sprint panel: where the cycle stands, and how it got there.
     *
     * The burn-up is drawn from completion timestamps, so it is a record rather than a projection —
     * every point is a ticket that actually closed on that day. The scope line is flat because the
     * API gives the scope as it stands now and not as it stood on each day; a sloping one would be
     * invention. The ideal line is the only thing here that is a construction, and it is drawn as
     * one — straight from zero to the scope across the cycle's days.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    public function sprint(Collection $rows): ?array
    {
        $current = $rows
            ->map(fn (array $row): ?array => $row['cycle'] ?? null)
            ->filter()
            ->filter(fn (array $cycle): bool => ($cycle['starts_at'] ?? '') !== ''
                && Carbon::now()->between(Carbon::parse($cycle['starts_at']), Carbon::parse($cycle['ends_at'] ?: $cycle['starts_at'])->endOfDay()))
            ->first();

        if ($current === null) {
            return null;
        }

        $mine = $rows->filter(fn (array $row): bool => ($row['cycle']['id'] ?? null) === $current['id'])->values();
        $from = Carbon::parse($current['starts_at'])->startOfDay();
        $to = Carbon::parse($current['ends_at'] ?: $current['starts_at'])->endOfDay();

        $done = $mine->filter(fn (array $row): bool => ($row['state_type'] ?? '') === 'completed');
        $closedPerDay = $done
            ->filter(fn (array $row): bool => $row['completed_at'] !== null)
            ->groupBy(fn (array $row): string => $row['completed_at']->toDateString())
            ->map(fn (Collection $group): int => $group->count());

        $days = [];
        $running = 0;
        $length = max(1, (int) $from->diffInDays($to));

        for ($day = $from->copy(), $i = 0; $day->lte($to); $day->addDay(), $i++) {
            $running += $closedPerDay[$day->toDateString()] ?? 0;

            $days[] = [
                'date' => $day->copy(),
                'done' => $running,
                'ideal' => round($mine->count() * ($i / $length), 2),
                'future' => $day->isFuture(),
            ];
        }

        return [
            'cycle' => $current,
            'from' => $from,
            'to' => $to,
            'scope' => $mine->count(),
            'started' => $mine->filter(fn (array $row): bool => ($row['state_type'] ?? '') === 'started')->count(),
            'done' => $done->count(),
            'points' => round($mine->sum(fn (array $row): float => (float) ($row['points'] ?? 0)), 1),
            'seconds' => $mine->sum(fn (array $row): int => (int) ($row['booked'] ?? 0)),
            'days' => $days,
            // the projects this sprint touches, biggest first — what the assignee list is for a team
            'projects' => $mine
                ->groupBy(fn (array $row): string => (string) ($row['project'] ?? ''))
                ->map(fn (Collection $group, string $project): array => [
                    'project' => $project === '' ? null : $project,
                    'count' => $group->count(),
                    'done' => $group->filter(fn (array $row): bool => ($row['state_type'] ?? '') === 'completed')->count(),
                ])
                ->sortByDesc('count')
                ->values()
                ->all(),
        ];
    }

    private function rank(string $type): int
    {
        $at = array_search($type, self::TYPE_ORDER, true);

        return $at === false ? count(self::TYPE_ORDER) : $at;
    }

    /** Linear's own position for a state, so two states of one type keep the workflow's order. */
    private function position(string $name, string $identifier, array $states): int
    {
        $team = explode('-', $identifier, 2)[0];

        return (int) ($states[$team][$name]['position'] ?? 0);
    }
}
