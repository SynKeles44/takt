<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Support\Parallel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads the day's own commits straight out of the local repositories — no service,
 * no token, nothing leaves the machine.
 */
final class Commits
{
    private const string FORMAT = '%H%x1f%h%x1f%an%x1f%ae%x1f%aI%x1f%s';

    /** @return Collection<int, array{project: Project, commits: list<array>, error: ?string}> */
    public function forDay(Carbon $day, ?Collection $projects = null): Collection
    {
        $projects ??= Project::query()->inOrder()->get();

        $logs = $this->readMany($projects, $day, $day);

        return $projects->map(fn (Project $project): array => [
            'project' => $project,
            'commits' => $logs[$project->getKey()] ?? [],
            'error' => $this->problem($project),
        ]);
    }

    /**
     * One git call per repository, all of them at once: four repositories cost the same
     * wall clock as one, and the page waits for all of them.
     *
     * @param  Collection<int, Project>  $projects
     * @return array<int, list<array>>
     */
    private function readMany(Collection $projects, Carbon $from, Carbon $to): array
    {
        $usable = $projects->filter(fn (Project $project): bool => $this->problem($project) === null);

        if ($usable->isEmpty()) {
            return [];
        }

        $emails = $this->emailsFor($usable);

        $outputs = Parallel::run($usable
            ->filter(fn (Project $project): bool => ($emails[$project->getKey()] ?? []) !== [])
            ->mapWithKeys(fn (Project $project): array => [
                'p'.$project->getKey() => $this->logArguments($project, $from, $to, $emails[$project->getKey()]),
            ])
            ->all());

        $commits = [];

        foreach ($outputs as $key => $output) {
            $commits[(int) substr((string) $key, 1)] = $this->parse($output);
        }

        return $commits;
    }

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, list<string>>
     */
    private function emailsFor(Collection $projects): array
    {
        $outputs = Parallel::run($projects->mapWithKeys(fn (Project $project): array => [
            'p'.$project->getKey() => ['git', '-C', $project->absolutePath(), 'config', 'user.email'],
        ])->all());

        $account = auth()->user()?->email;
        $emails = [];

        foreach ($projects as $project) {
            $found = [];
            $configured = trim($outputs['p'.$project->getKey()] ?? '');

            if ($configured !== '') {
                $found[] = $configured;
            }

            if ($account !== null && ! in_array($account, $found, true)) {
                $found[] = $account;
            }

            $emails[$project->getKey()] = $found;
        }

        return $emails;
    }

    /** @return list<string> */
    private function logArguments(Project $project, Carbon $from, Carbon $to, array $emails): array
    {
        $arguments = [
            'git', '-C', $project->absolutePath(), 'log',
            '--no-merges',
            '--since='.$from->copy()->startOfDay()->toDateTimeString(),
            '--until='.$to->copy()->endOfDay()->toDateTimeString(),
            '--pretty=format:'.self::FORMAT,
            '--all',
        ];

        foreach ($emails as $email) {
            $arguments[] = '--author='.$email;
        }

        return $arguments;
    }

    /** @return list<array{sha: string, short: string, author: string, at: Carbon, subject: string}> */
    private function parse(string $output): array
    {
        $commits = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            [$sha, $short, $author, $email, $at, $subject] = array_pad(explode("\x1f", $line), 6, '');

            $commits[$sha] = [
                'sha' => $sha,
                'short' => $short,
                'author' => $author,
                'email' => $email,
                'at' => Carbon::parse($at),
                'subject' => $subject,
            ];
        }

        return collect($commits)->sortByDesc('at')->values()->all();
    }

    /**
     * Commits per day over a range — one git call per repository, not one per day.
     *
     * @return Collection<string, int> keyed by Y-m-d
     */
    public function perDay(Carbon $from, Carbon $to, ?Collection $projects = null): Collection
    {
        $projects ??= Project::query()->inOrder()->get();

        $days = collect();

        for ($day = $from->copy()->startOfDay(); $day <= $to; $day->addDay()) {
            $days->put($day->toDateString(), 0);
        }

        foreach ($this->readMany($projects, $from, $to) as $commits) {
            foreach ($commits as $commit) {
                $key = $commit['at']->toDateString();

                if ($days->has($key)) {
                    $days->put($key, $days->get($key) + 1);
                }
            }
        }

        return $days;
    }

    public function count(Collection $groups): int
    {
        return $groups->sum(fn (array $group): int => count($group['commits']));
    }

    private function problem(Project $project): ?string
    {
        if (! $project->exists()) {
            return __('app.dev.missing_path', ['path' => $project->path]);
        }

        if (! $project->isGitRepository()) {
            return __('app.dev.no_repository');
        }

        return null;
    }
}
