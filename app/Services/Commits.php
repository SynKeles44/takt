<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;

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

        return $projects->map(fn (Project $project): array => [
            'project' => $project,
            'commits' => $this->read($project, $day, $day),
            'error' => $this->problem($project),
        ]);
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

        foreach ($projects as $project) {
            foreach ($this->read($project, $from, $to) as $commit) {
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

    /** @return list<array{sha: string, short: string, author: string, at: Carbon, subject: string}> */
    private function read(Project $project, Carbon $from, Carbon $to): array
    {
        if ($this->problem($project) !== null) {
            return [];
        }

        $emails = $this->emails($project);

        if ($emails === []) {
            return [];
        }

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

        $result = Process::timeout(15)->run($arguments);

        if ($result->failed()) {
            return [];
        }

        $commits = [];

        foreach (preg_split('/\R/', trim($result->output())) ?: [] as $line) {
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

    /** The identities that count as "mine" for this repository. */
    private function emails(Project $project): array
    {
        $result = Process::timeout(5)->run(['git', '-C', $project->absolutePath(), 'config', 'user.email']);

        $emails = [];

        if ($result->successful() && trim($result->output()) !== '') {
            $emails[] = trim($result->output());
        }

        $account = auth()->user()?->email;

        if ($account !== null && ! in_array($account, $emails, true)) {
            $emails[] = $account;
        }

        return $emails;
    }
}
