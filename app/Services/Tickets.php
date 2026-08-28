<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
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
     * @return array{tickets: Collection<int, array<string, mixed>>, error: ?string, configured: bool}
     */
    public function collect(User $user, int $days = self::DEFAULT_DAYS): array
    {
        $projects = Project::query()->inOrder()->get();
        $from = Carbon::today()->subDays($days)->startOfDay();
        $to = Carbon::today()->endOfDay();

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

        $seconds = $this->estimate($tickets, $from, $to);
        $mine = $this->linear->mine($user);

        $rows = [];

        // Linear first: these are the tickets, in the order Linear last touched them
        foreach ($mine['issues'] as $issue) {
            $id = $issue['id'];
            $git = $tickets[$id] ?? null;

            $rows[$id] = [
                'id' => $id,
                'title' => $issue['title'],
                'state' => $issue['state'],
                'state_type' => $issue['state_type'],
                'team' => $issue['team'],
                'assignee' => $issue['assignee'],
                'priority' => $issue['priority'],
                'url' => $issue['url'],
                'source' => 'linear',
                'projects' => array_values(array_unique($git['projects'] ?? [])),
                'commits' => $git['commits'] ?? [],
                'pulls' => $git['pulls'] ?? [],
                'branches' => $git['branches'] ?? [],
                'seconds' => $seconds[$id] ?? 0,
                'last' => $git['last'] ?? Carbon::parse($issue['updated_at'] === '' ? 'now' : $issue['updated_at']),
            ];
        }

        // then what the work shows but Linear does not — hiding it would hide the work
        foreach ($tickets as $id => $ticket) {
            if (isset($rows[$id])) {
                continue;
            }

            $rows[$id] = [
                'id' => $id,
                'title' => $ticket['title'],
                'state' => null,
                'state_type' => null,
                'team' => null,
                'assignee' => null,
                'priority' => null,
                'url' => null,
                'source' => 'git',
                'projects' => array_values(array_unique($ticket['projects'])),
                'commits' => $ticket['commits'],
                'pulls' => $ticket['pulls'],
                'branches' => $ticket['branches'],
                'seconds' => $seconds[$id] ?? 0,
                'last' => $ticket['last'],
            ];
        }

        return [
            'tickets' => collect($rows)
                ->sortByDesc(fn (array $row): string => $row['last']->toIso8601String())
                ->values(),
            'error' => $mine['error'],
            'configured' => $this->linear->configured($user),
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
