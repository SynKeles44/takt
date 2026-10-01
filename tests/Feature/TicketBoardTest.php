<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Models\Ticket;
use App\Models\TimeEntry;
use App\Services\TicketBoard;
use App\Services\Tickets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The local ticket layer: the columns of my day, the ignore flags, real time on a ticket, and
 * the calibration figure. Nothing here talks to Linear — that is the point of the split.
 */
class TicketBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->login(['email' => 'dev@example.test']);
    }

    public function test_a_linear_ticket_gets_no_local_row_until_something_local_is_said(): void
    {
        $this->assertSame(0, Ticket::query()->count());

        app(TicketBoard::class)->notes('COR-1', 'Etwas Lokales');

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame('Etwas Lokales', Ticket::query()->first()->notes);
    }

    public function test_an_ignored_id_disappears_from_the_found_list_and_is_counted(): void
    {
        $result = fn (): array => app(Tickets::class)->collect(auth()->user(), 30);

        app(TicketBoard::class)->ignore('COR-4');

        $this->assertSame(1, $result()['ignored']);
        $this->assertFalse($result()['loose']->contains('id', 'COR-4'));
    }

    public function test_local_keys_count_up_and_never_reuse_a_deleted_one(): void
    {
        $board = app(TicketBoard::class);

        $this->assertSame('TAKT-1', $board->create('Erstes')->key);
        $this->assertSame('TAKT-2', $board->create('Zweites')->key);

        Ticket::query()->where('key', 'TAKT-2')->delete();

        $this->assertSame('TAKT-2', $board->create('Drittes')->key);
    }

    public function test_time_booked_on_a_ticket_is_measured_not_split(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes');

        TimeEntry::query()->create([
            'type' => EntryType::Work,
            'ticket_id' => $ticket->getKey(),
            'started_at' => Carbon::today()->setTime(9, 0),
            'ended_at' => Carbon::today()->setTime(11, 30),
        ]);

        $row = app(Tickets::class)->collect(auth()->user(), 30)['tickets']->firstWhere('id', 'TAKT-1');

        $this->assertSame(9000, $row['booked']);
        // the even split must not be added on top of a measurement
        $this->assertSame(0, $row['split']);
        $this->assertSame(9000, $row['seconds']);
    }

    public function test_starting_a_timer_for_a_ticket_focuses_it(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes');

        $this->post(route('tickets.timer', ['key' => $ticket->key]))->assertRedirect();

        $ticket->refresh();

        $this->assertNotNull($ticket->focused_at);
        $this->assertSame($ticket->getKey(), TimeEntry::query()->running()->first()->ticket_id);
    }

    public function test_the_same_button_stops_a_timer_that_runs_for_this_ticket(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes');

        $this->post(route('tickets.timer', ['key' => $ticket->key]));
        $this->post(route('tickets.timer', ['key' => $ticket->key]));

        $this->assertNull(TimeEntry::query()->running()->first());
    }

    public function test_only_one_ticket_is_the_focus(): void
    {
        $board = app(TicketBoard::class);

        $board->focus('COR-5');
        $board->focus('COR-6');

        $this->assertSame('COR-6', $board->focused()->key);
        $this->assertSame(1, Ticket::query()->whereNotNull('focused_at')->count());
    }

    public function test_the_calibration_needs_three_comparable_tickets_and_ignores_guesses(): void
    {
        $tickets = app(Tickets::class);

        $rows = collect([
            ['estimate' => 3600, 'booked' => 7200],
            ['estimate' => 3600, 'booked' => 3600],
        ]);

        $this->assertNull($tickets->calibration($rows));

        // a split-evenly guess carries no estimate of mine, so it must not count
        $rows->push(['estimate' => null, 'booked' => 100000]);

        $this->assertNull($tickets->calibration($rows));

        $rows->push(['estimate' => 3600, 'booked' => 3600 * 2]);

        $calibration = $tickets->calibration($rows);

        $this->assertSame(3, $calibration['count']);
        // 7200 + 3600 + 7200 booked against 3 * 3600 estimated
        $this->assertSame(1.67, $calibration['factor']);
    }

    /**
     * A ticket that lives only here has no Linear state, so the board gives it a column of its
     * own — without that it would be a ticket the board simply does not show.
     */
    public function test_a_local_ticket_shows_up_in_its_own_column(): void
    {
        app(TicketBoard::class)->create('Eigenes Ticket');

        $this->page(route('tickets'))
            ->assertOk()
            ->assertSee(__('app.ticket.no_state'))
            ->assertSee('TAKT-1')
            ->assertSee('Eigenes Ticket');
    }

    public function test_the_ticket_file_opens_for_a_local_ticket(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes Ticket', 'Beschreibung dazu');

        $this->page(route('tickets.show', ['key' => $ticket->key]))
            ->assertOk()
            ->assertSee('Eigenes Ticket')
            ->assertSee(__('app.ticket.notes'))
            ->assertSee(__('app.ticket.timeline'));
    }

    public function test_notes_and_estimate_are_saved_and_never_leave_the_app(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes');

        $this->post(route('tickets.update', ['key' => $ticket->key]), [
            'notizen' => 'Erst Weber fragen',
            'schaetzung' => '1:30',
        ])->assertRedirect();

        $ticket->refresh();

        $this->assertSame('Erst Weber fragen', $ticket->notes);
        $this->assertSame(5400, $ticket->estimate_seconds);
    }

    public function test_the_route_pattern_keeps_the_static_segments_reachable(): void
    {
        // /tickets/anlegen must not be read as a ticket key
        $this->post(route('tickets.store'), ['titel' => 'Aus dem Formular'])->assertRedirect();

        $this->assertSame('Aus dem Formular', Ticket::query()->first()->title);
    }
}
