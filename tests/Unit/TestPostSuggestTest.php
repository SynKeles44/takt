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

    public function test_it_finds_the_instance_the_deploy_posted_into_the_ticket(): void
    {
        $filled = $this->suggest('COR-7100', [], [
            'description' => 'Siehe Beschreibung.',
            'comments' => [['body' => 'Review liegt auf https://b63d4865-web.galawork.dev/mod/zeiterfassung — bitte testen.']],
        ]);

        $this->assertSame('https://b63d4865-web.galawork.dev/mod/zeiterfassung', $filled['instance']);
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
    private function suggest(string $key, array $pulls, ?array $issue): array
    {
        return app(TestPost::class)->suggest(User::factory()->create(), $key, $pulls, $issue);
    }
}
