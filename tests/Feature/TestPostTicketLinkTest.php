<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TestPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The ticket link is Linear's own, not one assembled from a template.
 *
 * `…/issue/COR-1` and `…/issue/COR-1/der-titel` are not the same address: the slug is part of it.
 * A comment in this file used to claim it was optional, which is how the posted link ended up
 * pointing somewhere else than the ticket everybody else links to.
 */
class TestPostTicketLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_a_known_ticket_uses_the_url_linear_gives_it(): void
    {
        $user = $this->login(['linear_token' => 'lin_api_test']);
        $this->fakeLinear('https://linear.app/acme/issue/COR-1/der-lange-titel');

        $this->assertSame(
            'https://linear.app/acme/issue/COR-1/der-lange-titel',
            app(TestPost::class)->build($user, ['ticket' => 'COR-1'])['ticket'],
        );
    }

    /** Lower case in, Linear's own casing out — the key is looked up, not reformatted. */
    public function test_the_lookup_does_not_care_how_the_key_was_typed(): void
    {
        $user = $this->login(['linear_token' => 'lin_api_test']);
        $this->fakeLinear('https://linear.app/acme/issue/COR-1/der-lange-titel');

        $this->assertSame(
            'https://linear.app/acme/issue/COR-1/der-lange-titel',
            app(TestPost::class)->build($user, ['ticket' => 'cor-1'])['ticket'],
        );
    }

    /** A key Linear does not know still has to produce something, so the template stays. */
    public function test_an_unknown_key_falls_back_to_the_template(): void
    {
        $user = $this->login(['linear_token' => 'lin_api_test']);
        $this->fakeLinear(null);

        $this->assertSame(
            'https://linear.app/galawork/issue/COR-9999',
            app(TestPost::class)->build($user, ['ticket' => 'COR-9999'])['ticket'],
        );
    }

    /** No token means no lookup to make — and no request to send either. */
    public function test_without_a_token_it_builds_from_the_template_and_asks_nobody(): void
    {
        $user = $this->login();

        $this->assertSame(
            'https://linear.app/galawork/issue/COR-1',
            app(TestPost::class)->build($user, ['ticket' => 'COR-1'])['ticket'],
        );

        Http::assertNothingSent();
    }

    /** A pasted URL is the author's own and is passed through untouched. */
    public function test_a_pasted_url_is_left_alone(): void
    {
        $user = $this->login(['linear_token' => 'lin_api_test']);

        $this->assertSame(
            'https://linear.app/acme/issue/COR-5/etwas-anderes',
            app(TestPost::class)->build($user, ['ticket' => 'https://linear.app/acme/issue/COR-5/etwas-anderes'])['ticket'],
        );

        Http::assertNothingSent();
    }

    private function fakeLinear(?string $url): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['issues' => ['nodes' => $url === null ? [] : [[
            'identifier' => 'COR-1',
            'title' => 'Der lange Titel',
            'url' => $url,
            'updatedAt' => '2026-10-01T08:00:00.000Z',
            'state' => ['name' => 'Todo', 'type' => 'unstarted'],
            'team' => ['key' => 'COR', 'name' => 'Core'],
        ]]]]])]);
    }
}
