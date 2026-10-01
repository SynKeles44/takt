<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the last sprints actually looked like.
 *
 * Two halves from two systems, and the split is the point. Linear owns the sprint: which cycle a
 * ticket belongs to, when it runs, what counts as done. This app owns what was actually spent —
 * the hours, and which day they fell on. Neither one can answer "how did that sprint go" alone:
 * Linear knows the tickets closed and nothing about the time, the timesheet knows the time and
 * nothing about the tickets.
 *
 * So a sprint here is Linear's shape filled with this app's measurements, and where the two
 * disagree they are shown side by side rather than reconciled — booked-on-this-sprint's-tickets
 * and booked-during-this-sprint are different numbers, and the gap between them is information.
 */
final class Sprints
{
    /** How many cycles back to carry, newest first. */
    public const int DEFAULT_LIMIT = 6;

    public function __construct(private readonly Linear $linear) {}

    /**
     * @return array{sprints: Collection<int, array<string, mixed>>, error: ?string, configured: bool}
     */
    public function recent(User $user, int $limit = self::DEFAULT_LIMIT): array
    {
        $mine = $this->linear->mine($user);
        $grouped = [];

        foreach ($mine['issues'] as $issue) {
            $cycle = $issue['cycle'] ?? null;

            if ($cycle === null || ($cycle['starts_at'] ?? '') === '') {
                continue;
            }

            $grouped[$cycle['id']]['cycle'] = $cycle;
            $grouped[$cycle['id']]['issues'][] = $issue;
        }

        $sprints = collect($grouped)
            ->map(fn (array $group): array => $this->sprint($group['cycle'], $group['issues']))
            ->sortByDesc(fn (array $sprint): string => $sprint['from']->toIso8601String())
            ->take($limit)
            ->values();

        return [
            'sprints' => $sprints,
            'error' => $mine['error'],
            'configured' => $this->linear->configured($user),
        ];
    }

    /**
     * @param  array<string, mixed>  $cycle
     * @param  list<array<string, mixed>>  $issues
     * @return array<string, mixed>
     */
    private function sprint(array $cycle, array $issues): array
    {
        $from = Carbon::parse($cycle['starts_at'])->startOfDay();
        $to = ($cycle['ends_at'] ?? '') === '' ? $from->copy()->addDays(13)->endOfDay() : Carbon::parse($cycle['ends_at'])->endOfDay();

        $done = array_values(array_filter($issues, static fn (array $i): bool => $i['state_type'] === 'completed'));
        $started = array_values(array_filter($issues, static fn (array $i): bool => $i['state_type'] === 'started'));

        $keys = array_map(static fn (array $i): string => $i['id'], $issues);

        return [
            'id' => $cycle['id'],
            'number' => $cycle['number'],
            'name' => $cycle['name'],
            'from' => $from,
            'to' => $to,
            'current' => Carbon::now()->between($from, $to),
            'issues' => collect($issues)->sortBy('state_type')->values(),
            'scope' => count($issues),
            'done' => count($done),
            'started' => count($started),
            'points' => $this->points($issues),
            'points_done' => $this->points($done),
            // on this sprint's tickets, and during this sprint's days: two measurements, not one
            'seconds_on_issues' => $this->secondsOnTickets($keys),
            'seconds_in_window' => $this->secondsBetween($from, $to),
            'days' => $this->days($from, $to, $done),
        ];
    }

    /** @param  list<array<string, mixed>>  $issues */
    private function points(array $issues): float
    {
        return round(array_sum(array_map(
            static fn (array $issue): float => (float) ($issue['estimate'] ?? 0),
            $issues,
        )), 1);
    }

    /**
     * Work booked on the sprint's own tickets, whenever it happened — a ticket worked on before
     * the cycle started still belongs to the ticket.
     *
     * @param  list<string>  $keys
     */
    private function secondsOnTickets(array $keys): int
    {
        if ($keys === []) {
            return 0;
        }

        return (int) TimeEntry::query()
            ->completed()
            ->ofType(EntryType::Work)
            ->whereHas('ticket', fn ($query) => $query->whereIn('key', $keys))
            ->get()
            ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());
    }

    /** Everything worked during the sprint's days, ticket or not — the sprint's real cost. */
    private function secondsBetween(Carbon $from, Carbon $to): int
    {
        return (int) TimeEntry::query()
            ->completed()
            ->ofType(EntryType::Work)
            ->between($from, $to)
            ->get()
            ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());
    }

    /**
     * One row per day of the sprint: hours worked, and tickets finished that day.
     *
     * This is what the chart draws, and it is deliberately not a burndown. A burndown needs the
     * scope as it stood on each day, and Linear's API gives the scope as it stands now — drawing
     * one from today's numbers would be a straight line pretending to be a measurement.
     *
     * @param  list<array<string, mixed>>  $done
     * @return list<array<string, mixed>>
     */
    private function days(Carbon $from, Carbon $to, array $done): array
    {
        $end = $to->copy()->min(Carbon::now()->endOfDay());

        $worked = TimeEntry::query()
            ->completed()
            ->ofType(EntryType::Work)
            ->between($from, $end)
            ->get()
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString())
            ->map(fn (Collection $entries): int => (int) $entries->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()));

        $closed = collect($done)
            ->filter(static fn (array $issue): bool => ($issue['completed_at'] ?? '') !== '')
            ->groupBy(static fn (array $issue): string => Carbon::parse($issue['completed_at'])->toDateString())
            ->map(static fn (Collection $group): int => $group->count());

        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();

            $days[] = [
                'date' => $day->copy(),
                'seconds' => $worked[$key] ?? 0,
                'closed' => $closed[$key] ?? 0,
                'future' => $day->isFuture(),
                'weekend' => $day->isWeekend(),
            ];
        }

        return $days;
    }
}
