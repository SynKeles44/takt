<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Posts into Slack with the user's own token (xoxp-), so the message appears under the
 * user's name and avatar — a bot token would post as the app instead.
 */
final class Slack
{
    private const string API = 'https://slack.com/api/';

    public function configured(User $user): bool
    {
        return $user->slack_token !== null && $user->slack_channel !== null;
    }

    /**
     * @return array{ok: bool, permalink: ?string, error: ?string}
     */
    public function post(User $user, string $text): array
    {
        if (! $this->configured($user)) {
            return $this->failure(__('app.slack.not_configured'));
        }

        try {
            $response = Http::withToken($user->slack_token)
                ->asJson()
                ->timeout(15)
                ->post(self::API.'chat.postMessage', [
                    'channel' => $user->slack_channel,
                    'text' => $text,
                    // the three lines carry links; Slack must not turn them into previews
                    'unfurl_links' => false,
                    'unfurl_media' => false,
                ]);
        } catch (Throwable) {
            return $this->failure(__('app.slack.unreachable'));
        }

        $body = $response->json();

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            return $this->failure($this->explain(is_array($body) ? (string) ($body['error'] ?? '') : ''));
        }

        return [
            'ok' => true,
            'permalink' => $this->permalink($user, (string) ($body['channel'] ?? ''), (string) ($body['ts'] ?? '')),
            'error' => null,
        ];
    }

    /** The link to the message that was just posted — nice to have, never worth failing over. */
    private function permalink(User $user, string $channel, string $ts): ?string
    {
        if ($channel === '' || $ts === '') {
            return null;
        }

        try {
            $response = Http::withToken($user->slack_token)
                ->timeout(10)
                ->get(self::API.'chat.getPermalink', ['channel' => $channel, 'message_ts' => $ts]);
        } catch (Throwable) {
            return null;
        }

        $body = $response->json();

        return is_array($body) && ($body['ok'] ?? false) === true
            ? (string) $body['permalink']
            : null;
    }

    /** Slack's error codes in plain words — the ones that actually happen. */
    private function explain(string $code): string
    {
        return match ($code) {
            'invalid_auth', 'not_authed', 'token_revoked', 'token_expired' => __('app.slack.invalid_token'),
            'channel_not_found' => __('app.slack.channel_not_found'),
            'not_in_channel' => __('app.slack.not_in_channel'),
            'missing_scope' => __('app.slack.missing_scope'),
            'is_archived' => __('app.slack.archived'),
            'ratelimited' => __('app.slack.rate_limited'),
            '' => __('app.slack.failed', ['error' => '—']),
            default => __('app.slack.failed', ['error' => $code]),
        };
    }

    /** @return array{ok: bool, permalink: ?string, error: string} */
    private function failure(string $message): array
    {
        return ['ok' => false, 'permalink' => null, 'error' => $message];
    }
}
