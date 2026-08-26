<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The two lists that decide whether something is stuck: pull requests waiting for my review,
 * and my own pull requests waiting for someone else.
 *
 * The pull requests of a registered project are read per repository (`/repos/…/pulls`), not
 * through the search API: search silently returns nothing for a repository the token cannot
 * see, while the repository endpoint answers 404 — which is what lets Takt say "no access to
 * this repository" instead of showing an empty list. Search is still used on top, to catch
 * pull requests in repositories that are not registered as projects.
 *
 * Only strings and numbers go into the cache: Laravel refuses to unserialize classes from a
 * cache store, so dates are cached as ISO strings and turned back into Carbon on the way out.
 */
final class Reviews
{
    /** Ten minutes: a fetch costs over a second, and the refresh button is right there. */
    public const int CACHE_SECONDS = 600;

    public function configured(User $user): bool
    {
        return filled($user->github_token);
    }

    /** @return array{mine: list<array>, incoming: list<array>, repositories: array<string, array>, error: ?string, login: ?string, fetched_at: ?Carbon} */
    public function forUser(User $user): array
    {
        if (! $this->configured($user)) {
            return $this->empty();
        }

        $key = 'reviews.'.$user->getKey();
        $cached = Cache::get($key);

        if (! $this->usable($cached)) {
            $cached = $this->fetch($user);

            Cache::put($key, $cached, self::CACHE_SECONDS);
        }

        return $this->hydrate($cached);
    }

    /**
     * What is in the cache, without going to GitHub — fetching costs well over a second.
     *
     * @return array{mine: list<array>, incoming: list<array>, repositories: array<string, array>, error: ?string, login: ?string, fetched_at: ?Carbon}|null
     */
    public function cached(User $user): ?array
    {
        if (! $this->configured($user)) {
            return $this->empty();
        }

        $cached = Cache::get('reviews.'.$user->getKey());

        return $this->usable($cached) ? $this->hydrate($cached) : null;
    }

    public function forget(User $user): void
    {
        Cache::forget('reviews.'.$user->getKey());
    }

    /**
     * Every request in one pool: the identity, one call per repository, and the search on
     * top. Sequentially this took over three seconds — the requests do not depend on each
     * other, only the sorting afterwards does.
     *
     * @return array<string, mixed>
     */
    private function fetch(User $user): array
    {
        $slugs = $this->repositories();
        $responses = $this->pool($user, $slugs);
        $login = $this->loginFrom($responses['user'] ?? null);

        if ($login === null) {
            return [...$this->rawEmpty(), 'error' => __('app.dev.github_unauthorized')];
        }

        $repositories = [];
        $mine = [];
        $incoming = [];

        foreach ($slugs as $slug) {
            $result = $this->pulls($responses['repo:'.$slug] ?? null, $slug, $login);
            $repositories[$slug] = $result;

            $mine = [...$mine, ...$result['mine']];
            $incoming = [...$incoming, ...$result['incoming']];
        }

        $search = $this->search($responses['search'] ?? null);
        $known = array_map('strtolower', array_keys($repositories));

        foreach ($search['items'] as $pull) {
            if (! in_array(strtolower($pull['repository']), $known, true)) {
                $mine[] = $pull;
            }
        }

        return [
            'mine' => $this->sorted($mine),
            'incoming' => $this->sorted($incoming),
            'repositories' => $repositories,
            'login' => $login,
            'error' => $search['error'],
            'fetched_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $slugs
     * @return array<string, mixed> keyed responses, or a throwable per failed request
     */
    private function pool(User $user, array $slugs): array
    {
        $token = (string) $user->github_token;

        return Http::pool(function (Pool $pool) use ($slugs, $token): array {
            $request = fn (string $name) => $pool->as($name)
                ->withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(10);

            $calls = [$request('user')->get('https://api.github.com/user')];

            foreach ($slugs as $slug) {
                $calls[] = $request('repo:'.$slug)->get('https://api.github.com/repos/'.$slug.'/pulls', [
                    'state' => 'open',
                    'per_page' => 100,
                    'sort' => 'updated',
                    'direction' => 'desc',
                ]);
            }

            $calls[] = $request('search')->get('https://api.github.com/search/issues', [
                'q' => 'is:open is:pr author:@me archived:false',
                'per_page' => 20,
                'sort' => 'updated',
            ]);

            return $calls;
        });
    }

    private function loginFrom(mixed $response): ?string
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return null;
        }

        $login = $response->json('login');

        return is_string($login) && $login !== '' ? $login : null;
    }

    /** @return list<string> the repositories of the registered projects */
    private function repositories(): array
    {
        return Project::query()
            ->inOrder()
            ->get()
            ->map(fn (Project $project): ?string => $project->slug())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * One repository's open pull requests, split into mine and the ones waiting for me.
     *
     * @return array{status: string, message: ?string, mine: list<array>, incoming: list<array>}
     */
    private function pulls(mixed $response, string $slug, string $login): array
    {
        if (! $response instanceof Response) {
            return $this->repoProblem('unreachable', __('app.dev.github_unreachable'));
        }

        if (in_array($response->status(), [403, 404], true)) {
            return $this->repoProblem('no_access', __('app.dev.no_repo_access'));
        }

        if ($response->failed()) {
            return $this->repoProblem('error', __('app.dev.github_failed', ['status' => $response->status()]));
        }

        $mine = [];
        $incoming = [];

        foreach ($response->json() ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $pull = $this->pull($item, $slug);

            if (($item['user']['login'] ?? null) === $login) {
                $mine[] = $pull;

                continue;
            }

            $reviewers = collect($item['requested_reviewers'] ?? [])->pluck('login')->all();

            if (in_array($login, $reviewers, true)) {
                $incoming[] = $pull;
            }
        }

        return ['status' => 'ok', 'message' => null, 'mine' => $mine, 'incoming' => $incoming];
    }

    /** @return array{status: string, message: string, mine: list<array>, incoming: list<array>} */
    private function repoProblem(string $status, string $message): array
    {
        return ['status' => $status, 'message' => $message, 'mine' => [], 'incoming' => []];
    }

    /** @return array{items: list<array>, error: ?string} */
    private function search(mixed $response): array
    {
        if (! $response instanceof Response) {
            return ['items' => [], 'error' => __('app.dev.github_unreachable')];
        }

        if ($response->status() === 401) {
            return ['items' => [], 'error' => __('app.dev.github_unauthorized')];
        }

        if ($response->failed()) {
            return ['items' => [], 'error' => __('app.dev.github_failed', ['status' => $response->status()])];
        }

        $items = [];

        foreach ($response->json('items') ?? [] as $item) {
            $items[] = $this->pull($item, $this->repository((string) ($item['repository_url'] ?? '')));
        }

        return ['items' => $items, 'error' => null];
    }

    /** @return array<string, mixed> */
    private function pull(array $item, string $repository): array
    {
        return [
            'title' => (string) ($item['title'] ?? ''),
            'number' => (int) ($item['number'] ?? 0),
            'url' => (string) ($item['html_url'] ?? ''),
            'repository' => $repository,
            'draft' => (bool) ($item['draft'] ?? false),
            'updated_at' => Carbon::parse($item['updated_at'] ?? now())->toIso8601String(),
            'created_at' => Carbon::parse($item['created_at'] ?? now())->toIso8601String(),
        ];
    }

    /** @return list<array> */
    private function sorted(array $pulls): array
    {
        usort($pulls, fn (array $a, array $b): int => strcmp((string) $a['updated_at'], (string) $b['updated_at']));

        return array_values($pulls);
    }

    private function repository(string $url): string
    {
        return trim(str_replace('https://api.github.com/repos/', '', $url), '/');
    }

    /**
     * The pull requests of one repository, out of what was fetched anyway.
     *
     * @param  array{mine: list<array>}  $reviews
     * @return list<array>
     */
    public function mineFor(array $reviews, ?string $repository): array
    {
        if ($repository === null || $repository === '') {
            return [];
        }

        return array_values(array_filter(
            $reviews['mine'],
            fn (array $pull): bool => strcasecmp($pull['repository'], $repository) === 0,
        ));
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return $this->hydrate($this->rawEmpty());
    }

    /** @return array<string, mixed> */
    private function rawEmpty(): array
    {
        return ['mine' => [], 'incoming' => [], 'repositories' => [], 'login' => null, 'error' => null, 'fetched_at' => null];
    }

    private function usable(mixed $cached): bool
    {
        return is_array($cached)
            && is_array($cached['incoming'] ?? null)
            && is_array($cached['mine'] ?? null)
            && is_array($cached['repositories'] ?? null)
            && (! isset($cached['fetched_at']) || is_string($cached['fetched_at']));
    }

    /**
     * @param  array<string, mixed>  $cached
     * @return array<string, mixed>
     */
    private function hydrate(array $cached): array
    {
        $dates = static fn (array $items): array => array_map(static fn (array $item): array => [
            ...$item,
            'updated_at' => Carbon::parse($item['updated_at']),
            'created_at' => Carbon::parse($item['created_at']),
        ], $items);

        $repositories = [];

        foreach ($cached['repositories'] as $slug => $entry) {
            $repositories[$slug] = [
                ...$entry,
                'mine' => $dates($entry['mine']),
                'incoming' => $dates($entry['incoming']),
            ];
        }

        return [
            'incoming' => $dates($cached['incoming']),
            'mine' => $dates($cached['mine']),
            'repositories' => $repositories,
            'login' => $cached['login'] ?? null,
            'error' => $cached['error'] ?? null,
            'fetched_at' => isset($cached['fetched_at']) ? Carbon::parse($cached['fetched_at']) : null,
        ];
    }
}
