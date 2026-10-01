<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The ticket page, opened the way Linear's is: everything the issue carries, on one page.
 *
 * What makes this worth a test of its own is that most of it only exists in the single-issue
 * query. The board reads a hundred issues at once and must not pay for a description each; the
 * detail reads one and asks for everything. A field dropped from that query is invisible — the
 * page simply renders without it — so the page is asked for each one by name.
 */
class TicketDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Http::preventStrayRequests();

        $this->login(['linear_token' => 'lin_api_test']);
    }

    private function fakeIssue(array $overrides = []): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['issues' => ['nodes' => [[
            'identifier' => 'COR-4242',
            'title' => 'Buchung korrigieren',
            'description' => "Erste Zeile\nZweite Zeile",
            'url' => 'https://linear.app/acme/issue/COR-4242',
            'createdAt' => '2026-09-01T08:00:00.000Z',
            'updatedAt' => '2026-09-20T08:00:00.000Z',
            'startedAt' => '2026-09-10T08:00:00.000Z',
            'completedAt' => null,
            'dueDate' => '2026-10-15',
            'estimate' => 3,
            'branchName' => 'seymen/cor-4242-buchung',
            'priority' => 2,
            'priorityLabel' => 'High',
            'state' => ['name' => 'In Progress', 'type' => 'started'],
            'team' => ['key' => 'COR', 'name' => 'Core'],
            'assignee' => ['displayName' => 'Seymen'],
            'creator' => ['displayName' => 'Lukas'],
            'project' => ['name' => 'Abwesenheitsplaner'],
            'cycle' => ['id' => 'c1', 'number' => 11, 'name' => 'Cycle 11', 'startsAt' => '2026-09-28T00:00:00.000Z', 'endsAt' => '2026-10-11T00:00:00.000Z'],
            'labels' => ['nodes' => [['name' => 'bug', 'color' => '#ff0000']]],
            'parent' => ['identifier' => 'COR-4000', 'title' => 'Oberticket'],
            'children' => ['nodes' => [['identifier' => 'COR-4243', 'title' => 'Teilaufgabe', 'state' => ['name' => 'Todo', 'type' => 'unstarted']]]],
            'comments' => ['nodes' => [['body' => 'Schau mal drauf', 'createdAt' => '2026-09-21T09:00:00.000Z', 'user' => ['displayName' => 'Lukas']]]],
            ...$overrides,
        ]]]]])]);
    }

    public function test_the_page_carries_everything_the_issue_carries(): void
    {
        $this->fakeIssue();

        $response = $this->get(route('tickets.show', 'COR-4242'))->assertOk();

        foreach ([
            'Erste Zeile',              // description
            'Teilaufgabe',              // sub-issue
            'Schau mal drauf',          // comment
            'Lukas',                    // comment author and creator
            'Abwesenheitsplaner',       // project
            'Cycle 11',                 // sprint
            'Oberticket',               // parent
            'bug',                      // label
            '2026-10-15',               // due date
            'High',                     // priority
        ] as $fact) {
            $response->assertSee($fact);
        }
    }

    /**
     * The three things a ticket is reached by live in three other programs, so each one is one
     * click away rather than a selection of the right part of the page.
     */
    public function test_the_key_the_branch_and_the_link_can_be_copied(): void
    {
        $this->fakeIssue();

        $this->get(route('tickets.show', 'COR-4242'))
            ->assertOk()
            ->assertSee('data-copy="COR-4242"', escape: false)
            ->assertSee('data-copy="seymen/cor-4242-buchung"', escape: false)
            ->assertSee('data-copy="https://linear.app/acme/issue/COR-4242"', escape: false);
    }

    /**
     * Duplicating reads the original from Linear rather than from the page, so a copy made from a
     * tab opened yesterday is still a copy of what the ticket says now.
     */
    public function test_duplicating_creates_a_new_issue_from_the_originals_own_text(): void
    {
        /*
         * Answered by what is asked rather than in a fixed order: creating a copy makes four
         * requests — read the original, look up the team, look up me, write — and an order is a
         * detail of the service, not of the behaviour being tested.
         */
        Http::fake(function ($request) {
            $body = (string) $request->body();

            return match (true) {
                str_contains($body, 'query Issues') => Http::response(['data' => ['issues' => ['nodes' => [[
                    'identifier' => 'COR-4242',
                    'title' => 'Buchung korrigieren',
                    'description' => 'Der Hergang',
                    'state' => ['name' => 'In Progress', 'type' => 'started'],
                    'team' => ['key' => 'COR', 'name' => 'Core'],
                ]]]]]),
                // the team is looked for twice: first among my own issues, then any team at all
                str_contains($body, 'query Teams') => Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => []]]]]),
                str_contains($body, 'query AnyTeam') => Http::response(['data' => ['teams' => ['nodes' => [['id' => 't1']]]]]),
                str_contains($body, 'query Me') => Http::response(['data' => ['viewer' => ['id' => 'u1']]]),
                default => Http::response(['data' => ['issueCreate' => [
                    'success' => true,
                    'issue' => ['identifier' => 'COR-5000', 'url' => 'https://linear.app/acme/issue/COR-5000'],
                ]]]),
            };
        });

        $this->post(route('tickets.linear', 'COR-4242'), ['aktion' => 'duplizieren'])
            ->assertRedirect(route('tickets.show', 'COR-5000'));

        Http::assertSent(fn ($request): bool => str_contains((string) $request->body(), 'Kopie von Buchung korrigieren')
            && str_contains((string) $request->body(), 'Der Hergang'));
    }

    /** An issue without a description must not render an empty block where one would be. */
    public function test_an_issue_without_a_description_renders_no_description_block(): void
    {
        $this->fakeIssue(['description' => null, 'comments' => ['nodes' => []], 'children' => ['nodes' => []]]);

        $this->get(route('tickets.show', 'COR-4242'))
            ->assertOk()
            ->assertDontSee(__('app.ticket.description'))
            ->assertDontSee(__('app.ticket.comments'))
            ->assertDontSee(__('app.ticket.children'));
    }

    /**
     * The description is Linear's Markdown and is shown as text. Rendering it would mean emitting
     * HTML for content this app does not own, and a hand-written Markdown subset that emits HTML
     * is an XSS surface — so a description that contains a tag must arrive escaped.
     */
    public function test_a_description_cannot_bring_markup_with_it(): void
    {
        $this->fakeIssue(['description' => '<script>alert(1)</script>']);

        $this->get(route('tickets.show', 'COR-4242'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;', escape: false);
    }
}
