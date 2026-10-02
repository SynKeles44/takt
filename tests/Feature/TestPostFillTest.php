<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Aus Ticket füllen" for one ticket, then for another: the second fill must not keep what the
 * first one put into the fields when it finds nothing of its own.
 */
class TestPostFillTest extends TestCase
{
    use RefreshDatabase;

    private function github(string $conversation = 'deployed to https://abc123-web.galawork.dev'): void
    {
        Http::fake([
            'api.github.com/user' => Http::response(['login' => 'ich']),
            'api.github.com/search/issues*' => Http::response(['items' => [[
                'title' => 'COR-1 first ticket',
                'number' => 101,
                'html_url' => 'https://github.test/acme/web/pull/101',
                'repository_url' => 'https://api.github.com/repos/acme/web',
                'draft' => false,
                'updated_at' => '2026-10-01T10:00:00Z',
                'created_at' => '2026-10-01T09:00:00Z',
            ]]]),
            'api.github.com/repos/acme/web/pulls/101' => Http::response(['body' => $conversation]),
            'api.github.com/repos/acme/web/issues/101/comments' => Http::response([]),
        ]);
    }

    public function test_filling_a_second_ticket_clears_what_the_first_one_found(): void
    {
        $this->login(['github_token' => 'ghp_test']);
        $this->github();

        $first = $this->get(route('dev.testpost', ['ticket' => 'COR-1', 'fuellen' => '1']))->assertOk();

        $first->assertSee('value="101"', escape: false);
        $first->assertSee('value="https://abc123-web.galawork.dev"', escape: false);
        $first->assertSee('name="gefuellt" value="COR-1"', escape: false);

        // the form sends back what is in the fields — the first ticket's PR and instance — plus the marker
        $second = $this->get(route('dev.testpost', [
            'ticket' => 'COR-2',
            'pr' => '101',
            'instance' => 'https://abc123-web.galawork.dev',
            'gefuellt' => 'COR-1',
            'fuellen' => '1',
        ]))->assertOk();

        $second->assertDontSee('value="101"', escape: false);
        $second->assertDontSee('value="https://abc123-web.galawork.dev"', escape: false);
        $second->assertSee('name="gefuellt" value="COR-2"', escape: false);
    }

    public function test_filling_the_same_ticket_again_keeps_a_hand_made_correction(): void
    {
        $this->login(['github_token' => 'ghp_test']);
        // the PR is found again, but this time its text names no instance
        $this->github(conversation: 'no link in here');

        $this->get(route('dev.testpost', [
            'ticket' => 'COR-1',
            'pr' => '101',
            'instance' => 'handmade',
            'gefuellt' => 'COR-1',
            'fuellen' => '1',
        ]))
            ->assertOk()
            ->assertSee('value="handmade"', escape: false);
    }

    public function test_a_first_fill_keeps_what_was_typed_before_it(): void
    {
        $this->login(['github_token' => 'ghp_test']);
        $this->github();

        // nothing was filled yet, so a typed instance is the user's own, not a leftover
        $this->get(route('dev.testpost', ['ticket' => 'COR-3', 'instance' => 'typed', 'fuellen' => '1']))
            ->assertOk()
            ->assertSee('value="typed"', escape: false);
    }
}
