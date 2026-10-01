<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\LinearBoard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The board shaped like Linear's: columns that are the team's workflow, groups that are projects.
 *
 * It exists next to the day board rather than instead of it. Those five columns describe a day and
 * are mine; these are the team's, and a drop here writes a state back to Linear. Two boards for two
 * questions — which is also why the day board's tests still pass unchanged.
 */
class LinearBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Process::fake();
        Http::preventStrayRequests();
        Carbon::setTestNow('2026-10-01 12:00:00');

        $this->login(['linear_token' => 'lin_api_test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function row(string $id, ?string $state, string $type, ?string $project = null, ?array $cycle = null, ?string $completedAt = null): array
    {
        return [
            'id' => $id,
            'title' => 'Ticket '.$id,
            'state' => $state,
            'state_type' => $type,
            'project' => $project,
            'cycle' => $cycle,
            'points' => null,
            'booked' => 0,
            'completed_at' => $completedAt === null ? null : Carbon::parse($completedAt),
            'last' => Carbon::parse('2026-09-30 10:00'),
        ];
    }

    private function cycle(): array
    {
        return ['id' => 'c11', 'number' => 11, 'name' => 'Cycle 11', 'starts_at' => '2026-09-28T00:00:00.000Z', 'ends_at' => '2026-10-11T00:00:00.000Z'];
    }

    public function test_the_columns_are_the_states_in_the_workflows_own_order(): void
    {
        $rows = collect([
            $this->row('COR-1', 'Done', 'completed'),
            $this->row('COR-2', 'Todo', 'unstarted'),
            $this->row('COR-3', 'In Review', 'started'),
            $this->row('COR-4', 'In Progress', 'started'),
        ]);

        // In Progress sits before In Review in the team's workflow, and nothing else says so
        $states = ['COR' => [
            'In Progress' => ['id' => 's1', 'type' => 'started', 'position' => 1],
            'In Review' => ['id' => 's2', 'type' => 'started', 'position' => 2],
        ]];

        $columns = app(LinearBoard::class)->columns($rows, $states);

        $this->assertSame(['Todo', 'In Progress', 'In Review', 'Done'], array_column($columns, 'name'));
    }

    /**
     * Every state of the workflow is a column, including the ones nothing sits in.
     *
     * An empty column is information — it says where things go next — and a board that only draws
     * the states it happens to be occupying rearranges itself as you work.
     */
    public function test_a_state_with_no_tickets_still_gets_a_column(): void
    {
        $columns = app(LinearBoard::class)->columns(
            collect([$this->row('COR-1', 'Todo', 'unstarted')]),
            ['COR' => [
                'Todo' => ['id' => 's1', 'type' => 'unstarted', 'position' => 1],
                'In Progress' => ['id' => 's2', 'type' => 'started', 'position' => 2],
                'Done' => ['id' => 's3', 'type' => 'completed', 'position' => 3],
            ]],
        );

        $this->assertSame(['Todo', 'In Progress', 'Done'], array_column($columns, 'name'));
        $this->assertSame([1, 0, 0], array_column($columns, 'count'));
    }

    /** Which columns to draw is the user's decision, and an empty choice means all of them. */
    public function test_the_visible_columns_can_be_narrowed(): void
    {
        $states = ['COR' => [
            'Todo' => ['id' => 's1', 'type' => 'unstarted', 'position' => 1],
            'Done' => ['id' => 's2', 'type' => 'completed', 'position' => 2],
        ]];

        $rows = collect([$this->row('COR-1', 'Todo', 'unstarted')]);

        $this->assertSame(['Todo'], array_column(app(LinearBoard::class)->columns($rows, $states, ['Todo']), 'name'));
        $this->assertSame(['Todo', 'Done'], array_column(app(LinearBoard::class)->columns($rows, $states, null), 'name'));
    }

    public function test_the_chosen_columns_are_stored_on_the_account(): void
    {
        $this->post(route('tickets.states'), ['states' => ['Todo', 'Done', 'Todo']])->assertRedirect();

        $this->assertSame(['Todo', 'Done'], auth()->user()->fresh()->board_states);

        // nothing ticked is "show all", which is also what a new account has
        $this->post(route('tickets.states'), [])->assertRedirect();

        $this->assertNull(auth()->user()->fresh()->board_states);
    }

    /** A ticket that lives only here has no Linear state, and a board that drops it loses tickets. */
    public function test_a_local_ticket_gets_a_column_of_its_own_at_the_front(): void
    {
        $columns = app(LinearBoard::class)->columns(collect([
            $this->row('TAKT-1', null, ''),
            $this->row('COR-1', 'Todo', 'unstarted'),
        ]), []);

        $this->assertNull($columns[0]['name']);
        $this->assertSame(__('app.ticket.no_state'), $columns[0]['label']);
        $this->assertSame('Todo', $columns[1]['name']);
    }

    public function test_a_column_is_grouped_by_project_with_the_leftovers_last(): void
    {
        $columns = app(LinearBoard::class)->columns(collect([
            $this->row('COR-1', 'Todo', 'unstarted'),
            $this->row('COR-2', 'Todo', 'unstarted', 'Zebra'),
            $this->row('COR-3', 'Todo', 'unstarted', 'Alpha'),
        ]), []);

        $this->assertSame(['Alpha', 'Zebra', null], array_column($columns[0]['groups'], 'project'));
        $this->assertSame(3, $columns[0]['count']);
    }

    /**
     * The burn-up is a record, not a projection: every step up is a ticket that actually closed
     * that day, which is why it stops at today rather than running to the end of the cycle.
     */
    public function test_the_sprint_panel_counts_up_from_what_actually_closed(): void
    {
        $sprint = app(LinearBoard::class)->sprint(collect([
            $this->row('COR-1', 'Done', 'completed', 'Alpha', $this->cycle(), '2026-09-29T15:00:00.000Z'),
            $this->row('COR-2', 'In Progress', 'started', 'Alpha', $this->cycle()),
            $this->row('COR-3', 'Todo', 'unstarted', null, $this->cycle()),
        ]));

        $this->assertSame(3, $sprint['scope']);
        $this->assertSame(1, $sprint['started']);
        $this->assertSame(1, $sprint['done']);

        $days = collect($sprint['days']);

        $this->assertSame(0, $days->firstWhere(fn (array $d): bool => $d['date']->toDateString() === '2026-09-28')['done']);
        $this->assertSame(1, $days->firstWhere(fn (array $d): bool => $d['date']->toDateString() === '2026-09-29')['done']);
        // and it stays up: a burn-up never goes back down
        $this->assertSame(1, $days->firstWhere(fn (array $d): bool => $d['date']->toDateString() === '2026-10-01')['done']);
    }

    public function test_a_ticket_outside_the_running_cycle_has_no_sprint_panel(): void
    {
        $this->assertNull(app(LinearBoard::class)->sprint(collect([
            $this->row('COR-1', 'Todo', 'unstarted'),
        ])));
    }

    /**
     * The board opens on the running sprint, the way Linear's cycle board does — without it the
     * Done column carries every ticket closed in the window and buries the four that still need a
     * decision. It is a preselection, so the select shows it and an empty one asks for all.
     */
    public function test_the_board_opens_on_the_running_sprint(): void
    {
        Http::fake(['api.linear.app/graphql' => Http::response(['data' => ['viewer' => ['assignedIssues' => ['nodes' => [
            [
                'identifier' => 'COR-1', 'title' => 'Im Sprint', 'url' => '', 'updatedAt' => '2026-09-30T08:00:00.000Z',
                'state' => ['name' => 'Todo', 'type' => 'unstarted'], 'team' => ['key' => 'COR', 'name' => 'Core'],
                'cycle' => ['id' => 'c11', 'number' => 11, 'name' => 'Cycle 11', 'startsAt' => '2026-09-28T00:00:00.000Z', 'endsAt' => '2026-10-11T00:00:00.000Z'],
            ],
            [
                'identifier' => 'COR-2', 'title' => 'Alt und fertig', 'url' => '', 'updatedAt' => '2026-08-30T08:00:00.000Z',
                'state' => ['name' => 'Done', 'type' => 'completed'], 'team' => ['key' => 'COR', 'name' => 'Core'],
            ],
        ]]]]])]);

        $this->get(route('tickets'))
            ->assertOk()
            ->assertSee('Im Sprint')
            ->assertDontSee('Alt und fertig');

        // and asking for every sprint brings it back
        $this->get(route('tickets', ['sprint' => '']))->assertOk()->assertSee('Alt und fertig');
    }
}
