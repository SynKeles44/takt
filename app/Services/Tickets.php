<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Parallel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The tickets are Linear's — assigned to me, with their state, team and priority. Git only
 * enriches them: which projects touched the id, what was committed, which pull requests carry
 * it. An id that appears in the work but in no Linear issue is listed too, marked as git-only,
 * because dropping it would hide work that happened.
 *
 * The booked time is an estimate and says so: a day's working time is split evenly across the
 * tickets committed to that day. Git records no history of which branch was checked out when,
 * so anything more precise would be a guess dressed up as a measurement.
 */
final class Tickets
{
    /** `COR-6839`, `DEV-5472` — two or more letters, a dash, a number. */
    private const string PATTERN = '/\b([A-Z][A-Z0-9]{1,9})-(\d{1,6})\b/';

    public const int DEFAULT_DAYS = 90;

    public function __construct(
        private readonly Commits $commits,
        private readonly Reviews $reviews,
        private readonly TimeTracker $tracker,
        private readonly Linear $linear,
    ) {}

    /**
     * A row is a ticket: a Linear issue assigned to me, or one that exists only here. Git is
     * enrichment and never a row — the ids that appear only in commit subjects and branch names
     * come back separately as `loose`, because three quarters of them are residue from work long
     * done and letting them into the list is what made this area read as a commit viewer.
     *
     * @return array{
     *     tickets: Collection<int, array<string, mixed>>,
     *     loose: Collection<int, array<string, mixed>>,
     *     ignored: int,
     *     error: ?string,
     *     configured: bool,
     * }
     */
    public function collect(User $user, int $days = self::DEFAULT_DAYS): array
    {
        $from = Carbon::today()->subDays($days)->startOfDay();
        $to = Carbon::today()->endOfDay();

        $git = $this->fromGit($user, $from, $to);
        $split = $this->estimate($git, $from, $to);
        $booked = $this->booked();
        $mine = $this->linear->mine($user);

        $locals = Ticket::query()->get()->keyBy('key');
        $rows = [];

        // Linear first: these are the tickets, in the order Linear last touched them
        foreach ($mine['issues'] as $issue) {
            $id = $issue['id'];

            $rows[$id] = $this->row(
                id: $id,
                title: $issue['title'],
                source: 'linear',
                git: $git[$id] ?? null,
                local: $locals[$id] ?? null,
                split: $split[$id] ?? 0,
                booked: $booked[$id] ?? 0,
                linear: $issue,
            );
        }

        // then the tickets that exist only here — they behave exactly like the ones above
        foreach ($locals as $key => $local) {
            if (isset($rows[$key]) || $local->isIgnored() || ! $local->isLocal()) {
                continue;
            }

            $rows[$key] = $this->row(
                id: $key,
                title: (string) $local->title,
                source: 'local',
                git: $git[$key] ?? null,
                local: $local,
                split: $split[$key] ?? 0,
                booked: $booked[$key] ?? 0,
                linear: null,
            );
        }

        // an id the work shows but no ticket claims: a footnote, with actions, not a row
        $loose = [];

        foreach ($git as $id => $ticket) {
            if (isset($rows[$id]) || ($locals[$id] ?? null)?->isIgnored() === true) {
                continue;
            }

            $loose[$id] = $this->row(
                id: $id,
                title: $ticket['title'],
                source: 'git',
                git: $ticket,
                local: $locals[$id] ?? null,
                split: $split[$id] ?? 0,
                booked: $booked[$id] ?? 0,
                linear: null,
            );
        }

        return [
            'tickets' => collect($rows)
                ->sortByDesc(fn (array $row): string => $row['last']->toIso8601String())
                ->values(),
            'loose' => collect($loose)
                ->sortByDesc(fn (array $row): string => $row['last']->toIso8601String())
                ->values(),
            'ignored' => $locals->filter(fn (Ticket $ticket): bool => $ticket->isIgnored())->count(),
            'error' => $mine['error'],
            'configured' => $this->linear->configured($user),
        ];
    }

    /**
     * One row shape for all three origins, so a card renders the same whether the ticket came
     * from Linear, from here, or from a commit subject.
     *
     * @param  array<string, mixed>|null  $git
     * @param  array<string, mixed>|null  $linear
     * @return array<string, mixed>
     */
    private function row(
        string $id,
        ?string $title,
        string $source,
        ?array $git,
        ?Ticket $local,
        int $split,
        int $booked,
        ?array $linear,
    ): array {
        $last = $git['last'] ?? null;

        if ($last === null) {
            $updated = (string) ($linear['updated_at'] ?? '');
            $last = $updated === '' ? ($local?->updated_at ?? Carbon::now()) : Carbon::parse($updated);
        }

        return [
            'id' => $id,
            'title' => $title === null || $title === '' ? ($local?->title ?? null) : $title,
            'state' => $linear['state'] ?? null,
            'state_type' => $linear['state_type'] ?? null,
            'team' => $linear['team'] ?? null,
            'assignee' => $linear['assignee'] ?? null,
            'priority' => $linear['priority'] ?? null,
            'url' => $linear['url'] ?? $local?->promoted_url,
            'source' => $source,
            'projects' => array_values(array_unique($git['projects'] ?? [])),
            'commits' => $git['commits'] ?? [],
            'pulls' => $git['pulls'] ?? [],
            'branches' => $git['branches'] ?? [],
            // measured against guessed, kept apart on purpose: one is evidence, the other is not
            'booked' => $booked,
            'split' => $booked > 0 ? 0 : $split,
            'seconds' => $booked > 0 ? $booked : $split,
            'estimate' => $local?->estimate_seconds,
            'local' => $local,
            'column' => $local?->column,
            'last' => $last,
        ];
    }

    /**
     * Seconds actually booked per ticket key — the measurement that replaces the even split
     * wherever it exists.
     *
     * @return array<string, int>
     */
    private function booked(): array
    {
        $seconds = [];

        $entries = TimeEntry::query()
            ->completed()
            ->whereNotNull('ticket_id')
            ->with('ticket')
            ->get();

        foreach ($entries as $entry) {
            $key = $entry->ticket?->key;

            if ($key === null) {
                continue;
            }

            $seconds[$key] = ($seconds[$key] ?? 0) + $entry->durationInSeconds();
        }

        return $seconds;
    }

    /**
     * Everything git knows about ids in the window: which projects touched them, what was
     * committed, which branches carry them, which pull requests mention them.
     *
     * @return array<string, array<string, mixed>>
     */
    private function fromGit(User $user, Carbon $from, Carbon $to): array
    {
        $projects = Project::query()->inOrder()->get();
        $tickets = [];

        foreach ($this->commits->forRange($from, $to, $projects) as $projectId => $commits) {
            $project = $projects->firstWhere('id', $projectId);

            foreach ($commits as $commit) {
                foreach ($this->ids($commit['subject']) as $id) {
                    $this->touch($tickets, $id, $project?->name, $commit['at']);
                    $tickets[$id]['commits'][] = [...$commit, 'project' => $project?->name];
                    $tickets[$id]['title'] ??= $commit['subject'];
                }
            }
        }

        foreach ($this->branches($projects) as $projectName => $branches) {
            foreach ($branches as $branch) {
                foreach ($this->ids($branch['name']) as $id) {
                    $this->touch($tickets, $id, $projectName, $branch['at']);
                    $tickets[$id]['branches'][] = [...$branch, 'project' => $projectName];
                }
            }
        }

        $names = $projects
            ->filter(fn (Project $project): bool => $project->slug() !== null)
            ->mapWithKeys(fn (Project $project): array => [mb_strtolower((string) $project->slug()) => $project->name]);

        foreach ($this->pulls($user) as $pull) {
            // a pull request knows its repository slug; the list should read like the projects do
            $name = $names[mb_strtolower((string) $pull['repository'])] ?? $pull['repository'];

            foreach ($this->ids($pull['title']) as $id) {
                $this->touch($tickets, $id, $name, $pull['updated_at']);
                $tickets[$id]['pulls'][] = $pull;
                $tickets[$id]['title'] = $pull['title'];
            }
        }

        return $tickets;
    }

    /**
     * How my estimates compare to measured time. Only tickets that carry both a local estimate
     * and real booked time count — a split-evenly guess against an estimate would be two guesses
     * multiplied, and the factor would mean nothing.
     *
     * Null until there is something to say: with fewer than three comparable tickets a factor is
     * noise wearing a decimal point.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{factor: float, count: int, estimated: int, booked: int}|null
     */
    public function calibration(Collection $rows): ?array
    {
        $usable = $rows->filter(
            fn (array $row): bool => ($row['estimate'] ?? 0) > 0 && ($row['booked'] ?? 0) > 0,
        );

        if ($usable->count() < 3) {
            return null;
        }

        $estimated = (int) $usable->sum(fn (array $row): int => (int) $row['estimate']);
        $booked = (int) $usable->sum(fn (array $row): int => (int) $row['booked']);

        return [
            'factor' => $estimated === 0 ? 0.0 : round($booked / $estimated, 2),
            'count' => $usable->count(),
            'estimated' => $estimated,
            'booked' => $booked,
        ];
    }

    /** @return list<string> */
    public function ids(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches, PREG_SET_ORDER);

        $ids = [];

        foreach ($matches as $match) {
            $ids[] = $match[1].'-'.$match[2];
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string, array<string, mixed>> $tickets */
    private function touch(array &$tickets, string $id, ?string $project, Carbon $at): void
    {
        $tickets[$id] ??= [
            'title' => null,
            'projects' => [],
            'commits' => [],
            'pulls' => [],
            'branches' => [],
            'last' => $at,
        ];

        if ($project !== null) {
            $tickets[$id]['projects'][] = $project;
        }

        if ($at->greaterThan($tickets[$id]['last'])) {
            $tickets[$id]['last'] = $at;
        }
    }

    /**
     * A day's working time, split evenly across the tickets committed to that day. Stated as an
     * estimate everywhere it is shown.
     *
     * @param  array<string, array<string, mixed>>  $tickets
     * @return array<string, int>
     */
    private function estimate(array $tickets, Carbon $from, Carbon $to): array
    {
        $perDay = [];

        foreach ($tickets as $id => $ticket) {
            foreach ($ticket['commits'] as $commit) {
                $perDay[$commit['at']->toDateString()][$id] = true;
            }
        }

        $seconds = [];

        foreach ($perDay as $day => $ids) {
            $worked = $this->tracker->totalsForDay(Carbon::parse($day))['work'];

            if ($worked === 0) {
                continue;
            }

            $share = intdiv($worked, count($ids));

            foreach (array_keys($ids) as $id) {
                $seconds[$id] = ($seconds[$id] ?? 0) + $share;
            }
        }

        return $seconds;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<string, list<array{name: string, at: Carbon}>>
     */
    private function branches(Collection $projects): array
    {
        $usable = $projects->filter(fn (Project $project): bool => $project->exists() && $project->isGitRepository());

        if ($usable->isEmpty()) {
            return [];
        }

        $outputs = Parallel::run($usable->mapWithKeys(fn (Project $project): array => [
            'p'.$project->getKey() => [
                'git', '-C', $project->absolutePath(), 'for-each-ref',
                '--sort=-committerdate',
                '--count=200',
                '--format=%(refname:short)%1f%(committerdate:iso-strict)',
                'refs/heads', 'refs/remotes',
            ],
        ])->all());

        $branches = [];

        foreach ($outputs as $key => $output) {
            $project = $usable->firstWhere('id', (int) substr((string) $key, 1));
            $rows = [];

            foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
                if ($line === '') {
                    continue;
                }

                [$name, $at] = array_pad(explode("\x1f", $line), 2, '');

                if ($name === '') {
                    continue;
                }

                $rows[] = ['name' => $name, 'at' => Carbon::parse($at === '' ? 'now' : $at)];
            }

            $branches[$project?->name ?? (string) $key] = $rows;
        }

        return $branches;
    }

    /** @return list<array> */
    private function pulls(User $user): array
    {
        $reviews = $this->reviews->cached($user);

        return $reviews === null ? [] : [...$reviews['mine'], ...$reviews['incoming']];
    }
}
