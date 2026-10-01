<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\TestPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the ticket already knows about its own PR and its own test instance.
 *
 * The point of the last two cases: this fills a field or it leaves it alone. A suggestion that
 * guesses is worse than none, because the field looks answered and nobody checks it again.
 */
class TestPostSuggestTest extends TestCase
{
    use RefreshDatabase;

    private const array PULLS = [
        ['title' => 'COR-7100 AZK Abweichungen', 'number' => 2456],
        ['title' => 'COR-6944 Etwas anderes', 'number' => 2300],
    ];

    public function test_it_takes_the_pull_request_whose_title_carries_the_key(): void
    {
        $filled = $this->suggest('COR-7100', self::PULLS, null);

        $this->assertSame('2456', $filled['pr']);
        $this->assertSame(['pr'], $filled['found']);
        $this->assertSame(['instance'], $filled['missing']);
    }

    /**
     * The pull request is where the instance actually is: whatever deploys a review app announces
     * it in a comment there. This is the case the first version got wrong — it read the ticket
     * only, and the ticket carries the URL only if somebody copied it across by hand.
     */
    public function test_it_finds_the_instance_announced_on_the_pull_request(): void
    {
        $filled = $this->suggest('COR-7100', self::PULLS, null,
            fn (): string => 'Deployed: https://a64d8fda-web.galawork.dev — bitte testen.');

        $this->assertSame('https://a64d8fda-web.galawork.dev', $filled['instance']);
        $this->assertSame('2456', $filled['pr']);
    }

    /** The ticket is the fallback, for the case where somebody did copy it across. */
    public function test_it_falls_back_to_the_ticket_when_the_pull_request_is_silent(): void
    {
        $filled = $this->suggest('COR-7100', self::PULLS, [
            'description' => 'Siehe Beschreibung.',
            'comments' => [['body' => 'Review liegt auf https://b63d4865-web.galawork.dev/mod/zeiterfassung — bitte testen.']],
        ], fn (): string => 'Nichts dazu hier.');

        $this->assertSame('https://b63d4865-web.galawork.dev/mod/zeiterfassung', $filled['instance']);
    }

    /** No pull request means nothing to read, so the reader must not be called at all. */
    public function test_a_key_without_a_pull_request_reads_no_conversation(): void
    {
        $read = false;

        $this->suggest('COR-1234', self::PULLS, null, function () use (&$read): string {
            $read = true;

            return '';
        });

        $this->assertFalse($read);
    }

    /** A URL on some other host is somebody else's link, not this user's review instance. */
    public function test_a_url_that_does_not_match_the_template_is_not_offered(): void
    {
        $filled = $this->suggest('COR-7100', [], [
            'description' => 'Doku: https://example.com/handbuch und https://github.com/acme/web/pull/1',
            'comments' => [],
        ]);

        $this->assertSame('', $filled['instance']);
        $this->assertSame(['pr', 'instance'], $filled['missing']);
    }

    /** A pull request for a different ticket must not be offered for this one. */
    public function test_a_key_with_no_pull_request_fills_nothing(): void
    {
        $this->assertSame('', $this->suggest('COR-1234', self::PULLS, null)['pr']);
    }

    /** @param list<array<string, mixed>> $pulls */
    private function suggest(string $key, array $pulls, ?array $issue, ?callable $conversation = null): array
    {
        return app(TestPost::class)->suggest(User::factory()->create(), $key, $pulls, $issue, $conversation);
    }
}
