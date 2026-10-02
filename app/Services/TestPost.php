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
    public function __construct(private readonly Linear $linear) {}

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

        /*
         * Linear's own link for the issue, when Linear knows it.
         *
         * The template produces `…/issue/COR-7053` while Linear's is
         * `…/issue/COR-7053/abwesenheiten-werden-in-der-abwesenheitskachel-nicht-angezeigt` —
         * the title slug is part of the address, not decoration, and a comment here used to claim
         * it was optional. The real one was already in hand: `forIds` answers from the same cache
         * the board fills, so this costs a request only for a key nothing has looked at yet.
         */
        // a bare number first gets its team key back; the template cannot guess one
        $key = $this->linear->identify($user, $value);
        $known = $this->linear->forIds($user, [$key])['issues'][$key]['url'] ?? null;

        if (is_string($known) && $known !== '') {
            return $known;
        }

        // the template is the fallback: an unknown key, no token, or Linear not answering
        $template = $user->ticket_url_template ?: self::TICKET_DEFAULT;

        return str_replace(
            ['{key}', '{KEY}'],
            [Str::lower($key), $key],
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

    /**
     * What the ticket already knows about its own PR and its own test instance.
     *
     * Typing three fields by hand means looking up two of them somewhere else first, and both are
     * written down already: the pull request carries the ticket key in its title, and the review
     * instance has been posted into the ticket by whoever deployed it. Neither is guessed — a
     * value is only offered when it was found, and the caller is told which half came up empty.
     *
     * The pull request is searched FIRST and the ticket second, because that is where the instance
     * actually is: whatever deploys a review app announces it in a comment on the pull request,
     * and the ticket only ever carries it if somebody copied it across by hand.
     *
     * @param  list<array<string, mixed>>  $pulls  the user's pull requests, already fetched
     * @param  ?callable(string, int): string  $conversation  reads one pull request's text, called
     *                                                        only once a pull request was matched
     * @return array{pr: string, instance: string, repository: string, found: list<string>, missing: list<string>}
     */
    public function suggest(User $user, string $key, array $pulls, ?array $issue, ?callable $conversation = null): array
    {
        $key = mb_strtoupper(trim($key));

        $pr = '';
        $repository = '';
        $instance = '';

        foreach ($pulls as $pull) {
            if ($key !== '' && mb_stripos((string) $pull['title'], $key) !== false) {
                $pr = (string) $pull['number'];
                $repository = (string) ($pull['repository'] ?? '');
                break;
            }
        }

        if ($pr !== '' && $conversation !== null) {
            $instance = $this->instanceIn($user, $conversation($repository, (int) $pr));
        }

        if ($instance === '' && $issue !== null) {
            $instance = $this->instanceIn($user, implode("\n", [
                (string) ($issue['description'] ?? ''),
                ...array_map(static fn (array $c): string => (string) ($c['body'] ?? ''), $issue['comments'] ?? []),
            ]));
        }

        $fields = ['pr' => $pr, 'instance' => $instance];

        return [
            'pr' => $pr,
            'instance' => $instance,
            'repository' => $repository,
            'found' => array_keys(array_filter($fields)),
            'missing' => array_keys(array_filter($fields, static fn (string $v): bool => $v === '')),
        ];
    }

    /**
     * The first URL in the text that matches the user's own instance template.
     *
     * The template is the pattern — `https://{id}-web.galawork.dev{path}` becomes a regex whose
     * `{id}` is one host label and whose `{path}` is the rest. That is what keeps this from
     * being a guess: it finds a review instance for this user's setup, or it finds nothing.
     */
    private function instanceIn(User $user, string $text): string
    {
        $template = $user->instance_url_template ?: self::INSTANCE_DEFAULT;

        $pattern = '#'.str_replace(
            [preg_quote('{id}', '#'), preg_quote('{path}', '#')],
            ['[A-Za-z0-9][A-Za-z0-9-]*', '[^\s<>()\[\]"\']*'],
            preg_quote($template, '#'),
        ).'#';

        return preg_match($pattern, $text, $hit) === 1 ? $hit[0] : '';
    }
}
