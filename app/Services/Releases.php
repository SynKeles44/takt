<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Support\Parallel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * What was shipped when, read from the tags of the local repositories — no service, no token.
 * A tag is the honest answer to "since when is this live" as long as releases are tagged; a
 * project that tags nothing says so instead of showing an empty list.
 */
final class Releases
{
    private const string FORMAT = '%(refname:short)%1f%(creatordate:iso-strict)%1f%(subject)';

    public const int PER_PROJECT = 12;

    /** Five minutes: tags change rarely, and the palette must not start a git call per keystroke. */
    public const int CACHE_SECONDS = 300;

    /** @return Collection<int, array{project: Project, releases: list<array>, error: ?string}> */
    public function forProjects(?Collection $projects = null): Collection
    {
        $projects ??= Project::query()->inOrder()->get();

        $tags = $this->readMany($projects);

        Cache::put($this->key(), $this->flatten($tags), self::CACHE_SECONDS);

        return $projects->map(fn (Project $project): array => [
            'project' => $project,
            'releases' => $tags[$project->getKey()] ?? [],
            'error' => $this->problem($project),
        ]);
    }

    /**
     * What the last read left behind, without touching git. Only strings go into the store:
     * a cached Carbon comes back as an incomplete object and takes the page down with it.
     *
     * @return Collection<int, array{project: Project, releases: list<array>, error: ?string}>|null
     */
    public function cached(): ?Collection
    {
        $stored = Cache::get($this->key());

        if (! is_array($stored)) {
            return null;
        }

        return Project::query()->inOrder()->get()->map(fn (Project $project): array => [
            'project' => $project,
            'releases' => array_map(static fn (array $release): array => [
                ...$release,
                'at' => Carbon::parse($release['at']),
            ], $stored[$project->getKey()] ?? []),
            'error' => null,
        ]);
    }

    private function key(): string
    {
        return 'releases.'.(auth()->id() ?? 0);
    }

    /**
     * @param  array<int, list<array>>  $tags
     * @return array<int, list<array>>
     */
    private function flatten(array $tags): array
    {
        return array_map(
            static fn (array $releases): array => array_map(static fn (array $release): array => [
                ...$release,
                'at' => $release['at']->toIso8601String(),
            ], $releases),
            $tags,
        );
    }

    public function count(Collection $groups): int
    {
        return $groups->sum(fn (array $group): int => count($group['releases']));
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

    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, list<array>>
     */
    private function readMany(Collection $projects): array
    {
        $usable = $projects->filter(fn (Project $project): bool => $this->problem($project) === null);

        if ($usable->isEmpty()) {
            return [];
        }

        $outputs = Parallel::run($usable->mapWithKeys(fn (Project $project): array => [
            'p'.$project->getKey() => [
                'git', '-C', $project->absolutePath(), 'for-each-ref',
                // the last --sort is the primary key: date first, name as the tie-breaker,
                // because two tags created in the same second are otherwise unordered
                '--sort=-refname',
                '--sort=-creatordate',
                '--count='.self::PER_PROJECT,
                '--format='.self::FORMAT,
                'refs/tags',
            ],
        ])->all());

        $releases = [];

        foreach ($outputs as $key => $output) {
            $releases[(int) substr((string) $key, 1)] = $this->parse($output);
        }

        return $releases;
    }

    /** @return list<array{tag: string, at: Carbon, subject: string}> */
    private function parse(string $output): array
    {
        $releases = [];

        foreach (preg_split('/\R/', trim($output)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            [$tag, $at, $subject] = array_pad(explode("\x1f", $line), 3, '');

            if ($tag === '') {
                continue;
            }

            $releases[] = [
                'tag' => $tag,
                'at' => Carbon::parse($at === '' ? 'now' : $at),
                'subject' => $subject,
            ];
        }

        return $releases;
    }
}
