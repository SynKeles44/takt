<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The two lists that decide whether something is stuck: pull requests waiting for
 * my review, and my own pull requests waiting for someone else.
 */
final class Reviews
{
    public const int CACHE_SECONDS = 120;

    public function configured(User $user): bool
    {
        return filled($user->github_token);
    }

    /** @return array{mine: list<array>, incoming: list<array>, error: ?string, fetched_at: ?Carbon} */
    public function forUser(User $user): array
    {
        if (! $this->configured($user)) {
            return ['mine' => [], 'incoming' => [], 'error' => null, 'fetched_at' => null];
        }

        return Cache::remember(
            'reviews.'.$user->getKey(),
            self::CACHE_SECONDS,
            fn (): array => $this->fetch($user),
        );
    }

    public function forget(User $user): void
    {
        Cache::forget('reviews.'.$user->getKey());
    }

    private function fetch(User $user): array
    {
        $incoming = $this->search($user, 'is:open is:pr review-requested:@me archived:false');
        $mine = $this->search($user, 'is:open is:pr author:@me archived:false');

        $error = $incoming['error'] ?? $mine['error'] ?? null;

        return [
            'incoming' => $incoming['items'],
            'mine' => $mine['items'],
            'error' => $error,
            'fetched_at' => Carbon::now(),
        ];
    }

    /** @return array{items: list<array>, error: ?string} */
    private function search(User $user, string $query): array
    {
        try {
            $response = Http::withToken($user->github_token)
                ->withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(10)
                ->get('https://api.github.com/search/issues', ['q' => $query, 'per_page' => 20, 'sort' => 'updated']);
        } catch (\Throwable $exception) {
            return ['items' => [], 'error' => __('app.dev.github_unreachable')];
        }

        if ($response->status() === 401) {
            return ['items' => [], 'error' => __('app.dev.github_unauthorized')];
        }

        if ($response->failed()) {
            return ['items' => [], 'error' => __('app.dev.github_failed', ['status' => $response->status()])];
        }

        $items = collect($response->json('items') ?? [])
            ->map(fn (array $item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'number' => (int) ($item['number'] ?? 0),
                'url' => (string) ($item['html_url'] ?? ''),
                'repository' => $this->repository((string) ($item['repository_url'] ?? '')),
                'draft' => (bool) ($item['draft'] ?? false),
                'updated_at' => Carbon::parse($item['updated_at'] ?? now()),
                'created_at' => Carbon::parse($item['created_at'] ?? now()),
            ])
            ->sortBy('updated_at')
            ->values()
            ->all();

        return ['items' => $items, 'error' => null];
    }

    private function repository(string $url): string
    {
        return trim(str_replace('https://api.github.com/repos/', '', $url), '/');
    }
}
