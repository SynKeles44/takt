<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * What git cannot know about a ticket: its title, its state, who owns it, and where it lives.
 * Read from Linear's GraphQL API in one request for all ids on screen — the identifiers are
 * grouped by team key, which means a little overfetching in exchange for a single round trip.
 *
 * Only strings go into the cache: Laravel refuses to unserialize classes from a cache store.
 */
final class Linear
{
    private const string ENDPOINT = 'https://api.linear.app/graphql';

    /** Ten minutes, like the reviews: the rate limit is real and a ticket list is not live data. */
    public const int CACHE_SECONDS = 600;

    public function configured(User $user): bool
    {
        return filled($user->linear_token);
    }

    /**
     * @param  list<string>  $ids  identifiers in the form COR-6839
     * @return array{issues: array<string, array<string, string|null>>, error: ?string}
     */
    public function forIds(User $user, array $ids): array
    {
        if (! $this->configured($user) || $ids === []) {
            return ['issues' => [], 'error' => null];
        }

        $key = 'linear.'.$user->getKey();
        $cached = Cache::get($key);
        $known = is_array($cached) && is_array($cached['issues'] ?? null) ? $cached['issues'] : [];
        $missing = array_values(array_diff($ids, array_keys($known)));

        if ($missing === []) {
            return ['issues' => array_intersect_key($known, array_flip($ids)), 'error' => $cached['error'] ?? null];
        }

        $fetched = $this->fetch($user, $missing);

        if ($fetched['error'] !== null) {
            // a failed request must not leave ids behind as "unknown" — they would never be
            // asked for again until the cache expires
            return ['issues' => array_intersect_key($known, array_flip($ids)), 'error' => $fetched['error']];
        }

        // an id Linear does not know is remembered as unknown, so it is not asked again
        foreach ($missing as $id) {
            $known[$id] = $fetched['issues'][$id] ?? null;
        }

        Cache::put($key, ['issues' => $known, 'error' => null], self::CACHE_SECONDS);

        return ['issues' => array_intersect_key($known, array_flip($ids)), 'error' => $fetched['error']];
    }

    public function forget(User $user): void
    {
        Cache::forget('linear.'.$user->getKey());
        Cache::forget('linear.mine.'.$user->getKey());
    }

    /**
     * My own issues, straight from Linear — the source of the ticket list, not a lookup for ids
     * found in git. Deliberately without a server-side filter: the query stays minimal and the
     * open/closed split happens here, where a wrong guess about the filter syntax cannot break
     * the whole list.
     *
     * @return array{issues: list<array<string, mixed>>, error: ?string}
     */
    public function mine(User $user): array
    {
        if (! $this->configured($user)) {
            return ['issues' => [], 'error' => null];
        }

        $key = 'linear.mine.'.$user->getKey();
        $cached = Cache::get($key);

        if (is_array($cached) && is_array($cached['issues'] ?? null)) {
            return $cached;
        }

        $result = $this->fetchMine($user);

        if ($result['error'] === null) {
            Cache::put($key, $result, self::CACHE_SECONDS);
        }

        return $result;
    }

    /** @return array{issues: list<array<string, mixed>>, error: ?string} */
    private function fetchMine(User $user): array
    {
        $response = $this->post($user, <<<'GRAPHQL'
            query Mine {
              viewer {
                assignedIssues(first: 100, orderBy: updatedAt) {
                  nodes {
                    identifier
                    title
                    url
                    updatedAt
                    state { name type }
                    team { key name }
                    assignee { displayName }
                    priorityLabel
                  }
                }
              }
            }
        GRAPHQL, []);

        if ($response['error'] !== null) {
            return ['issues' => [], 'error' => $response['error']];
        }

        $issues = [];

        foreach ($response['data']['viewer']['assignedIssues']['nodes'] ?? [] as $node) {
            if (! is_array($node) || ! is_string($node['identifier'] ?? null)) {
                continue;
            }

            $issues[] = $this->issue($node);
        }

        return ['issues' => $issues, 'error' => null];
    }

    /** @return array<string, mixed> */
    private function issue(array $node): array
    {
        return [
            'id' => (string) $node['identifier'],
            'title' => (string) ($node['title'] ?? ''),
            'url' => (string) ($node['url'] ?? ''),
            'state' => (string) ($node['state']['name'] ?? ''),
            'state_type' => (string) ($node['state']['type'] ?? ''),
            'team' => $node['team']['name'] ?? null,
            'assignee' => $node['assignee']['displayName'] ?? null,
            'priority' => $node['priorityLabel'] ?? null,
            'updated_at' => (string) ($node['updatedAt'] ?? ''),
        ];
    }

    /**
     * One place where a request actually leaves — so the error wording, the header and the
     * timeout are decided once.
     *
     * @param  array<string, mixed>  $variables
     * @return array{data: array<string, mixed>, error: ?string}
     */
    private function post(User $user, string $query, array $variables): array
    {
        try {
            $response = Http::withHeaders([
                // Linear takes the personal key raw — no "Bearer" in front of it
                'Authorization' => (string) $user->linear_token,
                'Content-Type' => 'application/json',
            ])->timeout(10)->post(self::ENDPOINT, ['query' => $query, 'variables' => $variables]);
        } catch (Throwable) {
            return ['data' => [], 'error' => __('app.linear.unreachable')];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return ['data' => [], 'error' => __('app.linear.unauthorized')];
        }

        if ($response->failed()) {
            return ['data' => [], 'error' => __('app.linear.failed', ['status' => $response->status()])];
        }

        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            return ['data' => [], 'error' => __('app.linear.rejected', [
                'message' => (string) ($errors[0]['message'] ?? ''),
            ])];
        }

        return ['data' => $response->json('data') ?? [], 'error' => null];
    }

    /**
     * @param  list<string>  $ids
     * @return array{issues: array<string, array<string, string|null>>, error: ?string}
     */
    private function fetch(User $user, array $ids): array
    {
        $teams = [];
        $numbers = [];

        foreach ($ids as $id) {
            [$team, $number] = array_pad(explode('-', $id, 2), 2, '');

            if ($team === '' || ! ctype_digit($number)) {
                continue;
            }

            $teams[$team] = true;
            $numbers[(int) $number] = true;
        }

        if ($teams === []) {
            return ['issues' => [], 'error' => null];
        }

        $response = $this->post($user, <<<'GRAPHQL'
            query Issues($teams: [String!], $numbers: [Float!]) {
              issues(filter: { team: { key: { in: $teams } }, number: { in: $numbers } }, first: 100) {
                nodes {
                  identifier
                  title
                  url
                  updatedAt
                  state { name type }
                  team { key name }
                  assignee { displayName }
                  priorityLabel
                }
              }
            }
        GRAPHQL, [
            'teams' => array_keys($teams),
            'numbers' => array_map('floatval', array_keys($numbers)),
        ]);

        if ($response['error'] !== null) {
            return ['issues' => [], 'error' => $response['error']];
        }

        $issues = [];

        foreach ($response['data']['issues']['nodes'] ?? [] as $node) {
            if (! is_array($node) || ! is_string($node['identifier'] ?? null)) {
                continue;
            }

            $issues[$node['identifier']] = $this->issue($node);
        }

        return ['issues' => $issues, 'error' => null];
    }
}
