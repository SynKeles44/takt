<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Filtering the board, and setting Linear's state from a card.
 *
 * Both exist because of scale: with eighty-eight assigned tickets the board is only usable once it
 * can be narrowed, and walking to the ticket page to move one into review is three navigations for
 * a one-word change.
 */
class TicketFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Http::preventStrayRequests();

        $this->login(['linear_token' => 'lin_api_test']);
    }

    private function fakeBoard(): void
    {
        Http::fake(function ($request) {
            $body = (string) $request->body();

            if (str_contains($body, 'query States')) {
                return Http::response(['data' => ['issue' => ['team' => ['states' => ['nodes' => [
                    ['id' => 's1', 'name' => 'Todo', 'type' => 'unstarted', 'position' => 1],
                    ['id' => 's2', 'name' => 'In Review', 'type' => 'started', 'position' => 2],
                ]]]]]]);
            }

            return Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => [
                [
                    'identifier' => 'COR-1', 'title' => 'Mit Sprint', 'url' => '', 'updatedAt' => '2026-09-30T08:00:00.000Z',
                    'state' => ['name' => 'Todo', 'type' => 'unstarted'], 'team' => ['key' => 'COR', 'name' => 'Core'],
                    'project' => ['name' => 'Abwesenheitsplaner'],
                    'cycle' => ['id' => 'c1', 'number' => 11, 'name' => 'Cycle 11', 'startsAt' => '2026-09-28T00:00:00.000Z', 'endsAt' => '2026-10-11T00:00:00.000Z'],
                    'labels' => ['nodes' => [['name' => 'bug', 'color' => '#f00']]],
                ],
                [
                    'identifier' => 'COR-2', 'title' => 'Ohne alles', 'url' => '', 'updatedAt' => '2026-09-29T08:00:00.000Z',
                    'state' => ['name' => 'Todo', 'type' => 'unstarted'], 'team' => ['key' => 'COR', 'name' => 'Core'],
                ],
            ]]]]]);
        });
    }

    public function test_the_filters_offer_what_the_tickets_carry(): void
    {
        $this->fakeBoard();

        $this->page(route('tickets'))
            ->assertOk()
            ->assertSee('Cycle 11')
            ->assertSee('Abwesenheitsplaner')
            ->assertSee('bug');
    }

    public function test_a_picked_filter_narrows_the_board(): void
    {
        $this->fakeBoard();

        $this->page(route('tickets', ['projekt' => 'Abwesenheitsplaner']))
            ->assertOk()
            ->assertSee('Mit Sprint')
            ->assertDontSee('Ohne alles');
    }

    public function test_a_label_filter_matches_any_of_the_tickets_labels(): void
    {
        $this->fakeBoard();

        $this->page(route('tickets', ['label' => 'bug']))->assertOk()->assertSee('COR-1')->assertDontSee('COR-2');
        $this->page(route('tickets', ['label' => 'gibtsnicht']))->assertOk()->assertDontSee('COR-1');
    }

    /**
     * The workflow is read once per team, not once per card. On a board of eighty-eight tickets
     * the difference between those two is the difference between one request and eighty-eight.
     */
    public function test_the_card_offers_linears_states_and_asks_once_per_team(): void
    {
        $this->fakeBoard();

        $this->page(route('tickets'))
            ->assertOk()
            ->assertSee('data-state-select', escape: false)
            ->assertSee('In Review');

        Http::assertSentCount(2);
    }
}
