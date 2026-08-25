<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Builds the three-line block the testing channel expects. Accepts a bare ticket key
 * or PR number as well as a full URL, so pasting whatever is at hand works.
 */
final class TestPost
{
    public const string TICKET_DEFAULT = 'https://linear.app/galawork/issue/{KEY}';

    public const string PR_DEFAULT = 'https://github.com/galabau-workgroup/galawork-web/pull/{number}';

    public const string INSTANCE_DEFAULT = 'https://{id}-web.galawork.dev{path}';

    /** @return array{ticket: string, pr: string, instance: string, text: string, missing: list<string>} */
    public function build(User $user, array $input): array
    {
        $ticket = $this->ticket($user, (string) ($input['ticket'] ?? ''));
        $pr = $this->pullRequest($user, (string) ($input['pr'] ?? ''));
        $instance = $this->instance($user, (string) ($input['instance'] ?? ''));

        $missing = [];

        foreach (['ticket' => $ticket, 'pr' => $pr, 'instance' => $instance] as $key => $value) {
            if ($value === '') {
                $missing[] = $key;
            }
        }

        return [
            'ticket' => $ticket,
            'pr' => $pr,
            'instance' => $instance,
            'missing' => $missing,
            'text' => $this->text($ticket, $pr, $instance),
        ];
    }

    public function text(string $ticket, string $pr, string $instance): string
    {
        return implode("\n", [
            'Ticket: '.$ticket,
            'PR: '.$pr,
            'Test-Instanz: '.$instance,
        ]);
    }

    private function ticket(User $user, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $template = $user->ticket_url_template ?: self::TICKET_DEFAULT;

        // Linear keeps the key upper case; the title slug is optional in its URLs
        return str_replace(
            ['{key}', '{KEY}'],
            [Str::lower($value), Str::upper($value)],
            $template,
        );
    }

    private function pullRequest(User $user, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        $number = ltrim($value, '#');
        $template = $user->pr_url_template ?: self::PR_DEFAULT;

        return str_replace('{number}', $number, $template);
    }

    /**
     * One field for both halves: "b63d4865", "b63d4865/mod/zeiterfassung/?fn=…" or a
     * complete URL. Everything up to the first slash is the instance, the rest the path.
     */
    private function instance(User $user, string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        [$id, $path] = array_pad(explode('/', ltrim($value, '/'), 2), 2, '');

        $template = $user->instance_url_template ?: self::INSTANCE_DEFAULT;

        return str_replace(
            ['{id}', '{path}'],
            [$id, $path === '' ? '' : '/'.$path],
            $template,
        );
    }
}
