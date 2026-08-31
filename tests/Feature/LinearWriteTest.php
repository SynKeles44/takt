<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ticket;
use App\Services\Linear;
use App\Services\TicketBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Writing back to Linear. Only the fields Linear owns travel — a written state has to be resolved
 * to an id first, which is why a state change costs two requests and is worth asserting.
 */
class LinearWriteTest extends TestCase
{
    use RefreshDatabase;

    /** Answers the sequence a write makes: id lookup, then states if needed, then the mutation. */
    private function fake(array $responses): void
    {
        Http::fake(['api.linear.app/graphql' => Http::sequence(array_map(
            static fn (array $body): Response|array => $body,
            $responses,
        ))]);
    }

    private function bodyOf(Request $request): string
    {
        return (string) ($request->data()['query'] ?? '');
    }

    public function test_a_title_change_resolves_the_issue_id_and_sends_one_mutation(): void
    {
        $this->fake([
            ['data' => ['issue' => ['id' => 'uuid-1']]],
            ['data' => ['issueUpdate' => ['success' => true]]],
        ]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        $result = app(Linear::class)->update($user, 'COR-1', ['title' => 'Neuer Titel']);

        $this->assertTrue($result['ok']);
        $this->assertNull($result['error']);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($this->bodyOf($request), 'issueUpdate')
            && ($request->data()['variables']['input']['title'] ?? null) === 'Neuer Titel');
    }

    public function test_a_state_change_is_translated_from_the_name_to_the_id(): void
    {
        $this->fake([
            ['data' => ['issue' => ['id' => 'uuid-1']]],
            ['data' => ['issue' => ['team' => ['states' => ['nodes' => [
                ['id' => 'state-todo', 'name' => 'Todo', 'type' => 'unstarted'],
                ['id' => 'state-done', 'name' => 'Done', 'type' => 'completed'],
            ]]]]]],
            ['data' => ['issueUpdate' => ['success' => true]]],
        ]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        // lower case on purpose: the name comes from a text field a person typed
        $this->assertTrue(app(Linear::class)->update($user, 'COR-1', ['state' => 'done'])['ok']);

        Http::assertSent(fn (Request $request): bool => str_contains($this->bodyOf($request), 'issueUpdate')
            && ($request->data()['variables']['input']['stateId'] ?? null) === 'state-done');
    }

    public function test_a_state_linear_does_not_know_is_refused_before_anything_is_written(): void
    {
        $this->fake([
            ['data' => ['issue' => ['id' => 'uuid-1']]],
            ['data' => ['issue' => ['team' => ['states' => ['nodes' => [
                ['id' => 'state-todo', 'name' => 'Todo', 'type' => 'unstarted'],
            ]]]]]],
        ]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        $result = app(Linear::class)->update($user, 'COR-1', ['state' => 'Erledigt']);

        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);

        // two lookups and no mutation: a rejected write must not half-apply
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request): bool => str_contains($this->bodyOf($request), 'issueUpdate'));
    }

    public function test_nothing_to_change_sends_nothing(): void
    {
        $this->fake([['data' => ['issue' => ['id' => 'uuid-1']]]]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        $this->assertTrue(app(Linear::class)->update($user, 'COR-1', [])['ok']);

        Http::assertNotSent(fn (Request $request): bool => str_contains($this->bodyOf($request), 'issueUpdate'));
    }

    public function test_a_comment_reaches_the_issue(): void
    {
        $this->fake([
            ['data' => ['issue' => ['id' => 'uuid-1']]],
            ['data' => ['commentCreate' => ['success' => true]]],
        ]);

        $user = $this->login(['linear_token' => 'lin_api_test']);

        $this->assertTrue(app(Linear::class)->comment($user, 'COR-1', 'Warte auf Weber')['ok']);

        Http::assertSent(fn (Request $request): bool => ($request->data()['variables']['input']['body'] ?? null) === 'Warte auf Weber');
    }

    public function test_a_local_ticket_keeps_its_notes_and_column_when_it_becomes_a_linear_issue(): void
    {
        $this->fake([
            ['data' => ['viewer' => ['assignedIssues' => ['nodes' => [['team' => ['id' => 'team-1']]]]]]],
            ['data' => ['viewer' => ['id' => 'me']]],
            ['data' => ['issueCreate' => [
                'success' => true,
                'issue' => ['identifier' => 'COR-4242', 'url' => 'https://linear.app/acme/issue/COR-4242'],
            ]]],
        ]);

        $this->login(['linear_token' => 'lin_api_test']);

        $board = app(TicketBoard::class);
        $ticket = $board->create('Serverwechsel prüfen', 'Vorher Backup');
        $board->notes($ticket->key, 'Erst Weber fragen');
        $board->estimate($ticket->key, 5400);

        $this->post(route('tickets.linear', ['key' => $ticket->key]), ['aktion' => 'anlegen'])
            ->assertRedirect(route('tickets.show', ['key' => 'COR-4242']));

        $ticket->refresh();

        $this->assertSame('COR-4242', $ticket->key);
        $this->assertSame('linear', $ticket->source);
        $this->assertSame('Erst Weber fragen', $ticket->notes);
        $this->assertSame(5400, $ticket->estimate_seconds);
        $this->assertSame('https://linear.app/acme/issue/COR-4242', $ticket->promoted_url);
        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_a_refused_creation_leaves_the_local_ticket_untouched(): void
    {
        $this->fake([
            ['data' => ['viewer' => ['assignedIssues' => ['nodes' => [['team' => ['id' => 'team-1']]]]]]],
            ['data' => ['viewer' => ['id' => 'me']]],
            ['data' => ['issueCreate' => ['success' => false, 'issue' => null]]],
        ]);

        $this->login(['linear_token' => 'lin_api_test']);

        $ticket = app(TicketBoard::class)->create('Bleibt lokal');

        $this->post(route('tickets.linear', ['key' => $ticket->key]), ['aktion' => 'anlegen']);

        $ticket->refresh();

        $this->assertSame('TAKT-1', $ticket->key);
        $this->assertSame('local', $ticket->source);
        $this->assertNull($ticket->promoted_url);
    }
}
