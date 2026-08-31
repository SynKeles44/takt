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
                // an empty PHP array encodes to [], and Linear rejects that: variables must be
                // an object, so a query without variables sends none at all
            ])->timeout(10)->post(self::ENDPOINT, array_filter(
                ['query' => $query, 'variables' => $variables],
                static fn (mixed $value): bool => $value !== [],
            ));
        } catch (Throwable) {
            return ['data' => [], 'error' => __('app.linear.unreachable')];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return ['data' => [], 'error' => __('app.linear.unauthorized')];
        }

        /*
         * GraphQL puts the reason in the body, also on a 400 — reading the status first turned
         * "Cannot query field X" into a bare "answers with 400", which says nothing about what
         * to fix.
         */
        $errors = $response->json('errors');

        if (is_array($errors) && $errors !== []) {
            return ['data' => [], 'error' => __('app.linear.rejected', [
                'message' => (string) ($errors[0]['message'] ?? ''),
            ])];
        }

        if ($response->failed()) {
            return ['data' => [], 'error' => __('app.linear.failed', ['status' => $response->status()])];
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

    /**
     * MARK: writing back
     *
     * Only the fields Linear owns travel this way — title, description, state, priority,
     * estimate, assignee, comments. My column, my notes and my local estimate stay here; putting
     * them into a shared tracker would be a bug, not a feature.
     *
     * The state and assignee ids Linear needs are not the names shown in the UI, so a write that
     * changes state resolves the team's workflow states first. That costs one extra request and
     * is the reason this is not a single generic setter.
     */

    /** @return array{ok: bool, error: ?string} */
    public function update(User $user, string $identifier, array $fields): array
    {
        $id = $this->issueId($user, $identifier);

        if ($id === null) {
            return ['ok' => false, 'error' => __('app.linear.unknown_issue', ['id' => $identifier])];
        }

        $input = [];

        foreach (['title', 'description'] as $key) {
            if (array_key_exists($key, $fields) && is_string($fields[$key])) {
                $input[$key] = $fields[$key];
            }
        }

        if (array_key_exists('priority', $fields) && $fields['priority'] !== null) {
            $input['priority'] = (int) $fields['priority'];
        }

        if (array_key_exists('estimate', $fields) && $fields['estimate'] !== null) {
            $input['estimate'] = (int) $fields['estimate'];
        }

        if (array_key_exists('state', $fields) && is_string($fields['state']) && $fields['state'] !== '') {
            $stateId = $this->stateId($user, $identifier, $fields['state']);

            if ($stateId === null) {
                return ['ok' => false, 'error' => __('app.linear.unknown_state', ['state' => $fields['state']])];
            }

            $input['stateId'] = $stateId;
        }

        if (array_key_exists('assignToMe', $fields)) {
            $me = $this->viewerId($user);

            if ($me === null) {
                return ['ok' => false, 'error' => __('app.linear.unknown_viewer')];
            }

            $input['assigneeId'] = $fields['assignToMe'] === true ? $me : null;
        }

        if ($input === []) {
            return ['ok' => true, 'error' => null];
        }

        $response = $this->post($user, <<<'GRAPHQL'
            mutation Update($id: String!, $input: IssueUpdateInput!) {
              issueUpdate(id: $id, input: $input) { success }
            }
        GRAPHQL, ['id' => $id, 'input' => $input]);

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }

        $this->forget($user);

        return ['ok' => ($response['data']['issueUpdate']['success'] ?? false) === true, 'error' => null];
    }

    /** @return array{ok: bool, error: ?string} */
    public function comment(User $user, string $identifier, string $body): array
    {
        $id = $this->issueId($user, $identifier);

        if ($id === null) {
            return ['ok' => false, 'error' => __('app.linear.unknown_issue', ['id' => $identifier])];
        }

        $response = $this->post($user, <<<'GRAPHQL'
            mutation Comment($input: CommentCreateInput!) {
              commentCreate(input: $input) { success }
            }
        GRAPHQL, ['input' => ['issueId' => $id, 'body' => $body]]);

        if ($response['error'] !== null) {
            return ['ok' => false, 'error' => $response['error']];
        }

        return ['ok' => ($response['data']['commentCreate']['success'] ?? false) === true, 'error' => null];
    }

    /**
     * Create a Linear issue from a local ticket. The team is the one I work in most — read from
     * the issues already assigned to me, so no team has to be configured by hand.
     *
     * @return array{ok: bool, url: ?string, identifier: ?string, error: ?string}
     */
    public function create(User $user, string $title, ?string $body = null): array
    {
        $teamId = $this->teamId($user);

        if ($teamId === null) {
            return ['ok' => false, 'url' => null, 'identifier' => null, 'error' => __('app.linear.unknown_team')];
        }

        $input = array_filter([
            'teamId' => $teamId,
            'title' => $title,
            'description' => $body,
            'assigneeId' => $this->viewerId($user),
        ], static fn (mixed $value): bool => $value !== null);

        $response = $this->post($user, <<<'GRAPHQL'
            mutation Create($input: IssueCreateInput!) {
              issueCreate(input: $input) {
                success
                issue { identifier url }
              }
            }
        GRAPHQL, ['input' => $input]);

        if ($response['error'] !== null) {
            return ['ok' => false, 'url' => null, 'identifier' => null, 'error' => $response['error']];
        }

        $created = $response['data']['issueCreate'] ?? [];

        $this->forget($user);

        return [
            'ok' => ($created['success'] ?? false) === true,
            'url' => $created['issue']['url'] ?? null,
            'identifier' => $created['issue']['identifier'] ?? null,
            'error' => null,
        ];
    }

    /** The workflow states of the team an issue belongs to, name => id. */
    public function states(User $user, string $identifier): array
    {
        $response = $this->post($user, <<<'GRAPHQL'
            query States($id: String!) {
              issue(id: $id) {
                team {
                  states { nodes { id name type position } }
                }
              }
            }
        GRAPHQL, ['id' => $identifier]);

        $states = [];

        foreach ($response['data']['issue']['team']['states']['nodes'] ?? [] as $node) {
            if (is_array($node) && is_string($node['name'] ?? null) && is_string($node['id'] ?? null)) {
                $states[(string) $node['name']] = ['id' => $node['id'], 'type' => (string) ($node['type'] ?? '')];
            }
        }

        return $states;
    }

    private function stateId(User $user, string $identifier, string $name): ?string
    {
        $states = $this->states($user, $identifier);

        // Linear's own name first; a case-insensitive match second, so "todo" finds "Todo"
        if (isset($states[$name])) {
            return $states[$name]['id'];
        }

        foreach ($states as $stateName => $state) {
            if (mb_strtolower($stateName) === mb_strtolower($name)) {
                return $state['id'];
            }
        }

        return null;
    }

    /**
     * Linear's mutations want the internal uuid, not the identifier shown everywhere. The
     * identifier resolves to it through a plain issue lookup, which Linear accepts.
     */
    private function issueId(User $user, string $identifier): ?string
    {
        $response = $this->post($user, <<<'GRAPHQL'
            query Id($id: String!) { issue(id: $id) { id } }
        GRAPHQL, ['id' => $identifier]);

        $id = $response['data']['issue']['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    private function viewerId(User $user): ?string
    {
        $response = $this->post($user, <<<'GRAPHQL'
            query Me { viewer { id } }
        GRAPHQL, []);

        $id = $response['data']['viewer']['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /** The team most of my assigned issues live in — a majority vote, not a configured value. */
    private function teamId(User $user): ?string
    {
        $response = $this->post($user, <<<'GRAPHQL'
            query Teams {
              viewer {
                assignedIssues(first: 50) { nodes { team { id } } }
              }
            }
        GRAPHQL, []);

        $counts = [];

        foreach ($response['data']['viewer']['assignedIssues']['nodes'] ?? [] as $node) {
            $id = $node['team']['id'] ?? null;

            if (is_string($id)) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            $teams = $this->post($user, <<<'GRAPHQL'
                query AnyTeam { teams(first: 1) { nodes { id } } }
            GRAPHQL, []);

            $id = $teams['data']['teams']['nodes'][0]['id'] ?? null;

            return is_string($id) ? $id : null;
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }
}
