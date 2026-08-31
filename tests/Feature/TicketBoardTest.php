<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EntryType;
use App\Enums\TicketColumn;
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

        app(TicketBoard::class)->place('COR-1', TicketColumn::Today);

        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(TicketColumn::Today, Ticket::query()->first()->column);
    }

    public function test_the_column_timestamp_moves_on_a_move_but_not_on_a_reorder(): void
    {
        $board = app(TicketBoard::class);

        Carbon::setTestNow('2026-08-20 09:00');
        $board->place('COR-2', TicketColumn::Waiting);

        $first = Ticket::query()->where('key', 'COR-2')->first()->column_changed_at;

        // same column again: this is a reorder, and it must not reset "how long has this been stuck"
        Carbon::setTestNow('2026-08-25 09:00');
        $board->place('COR-2', TicketColumn::Waiting);

        $this->assertTrue($first->equalTo(Ticket::query()->where('key', 'COR-2')->first()->column_changed_at));

        $board->place('COR-2', TicketColumn::Today);

        $this->assertSame('2026-08-25', Ticket::query()->where('key', 'COR-2')->first()->column_changed_at->toDateString());

        Carbon::setTestNow();
    }

    public function test_a_waiting_reason_belongs_to_the_stay_not_to_the_ticket(): void
    {
        $board = app(TicketBoard::class);

        $board->place('COR-3', TicketColumn::Waiting);
        $board->waitingReason('COR-3', 'Review von Weber');

        $this->assertSame('Review von Weber', Ticket::query()->where('key', 'COR-3')->first()->waiting_reason);

        $board->place('COR-3', TicketColumn::Today);

        $this->assertNull(Ticket::query()->where('key', 'COR-3')->first()->waiting_reason);
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

    public function test_starting_a_timer_for_a_ticket_moves_it_to_today_and_focuses_it(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes', column: TicketColumn::Next);

        $this->post(route('tickets.timer', ['key' => $ticket->key]))->assertRedirect();

        $ticket->refresh();

        $this->assertSame(TicketColumn::Today, $ticket->column);
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

    public function test_a_finished_ticket_leaves_the_board_after_a_week(): void
    {
        $board = app(TicketBoard::class);

        Carbon::setTestNow(Carbon::now()->subDays(TicketColumn::DONE_DAYS + 1));
        $board->place('COR-7', TicketColumn::Done);
        Carbon::setTestNow();

        $board->place('COR-8', TicketColumn::Done);

        $rows = collect([
            ['id' => 'COR-7', 'column' => TicketColumn::Done, 'local' => Ticket::query()->where('key', 'COR-7')->first()],
            ['id' => 'COR-8', 'column' => TicketColumn::Done, 'local' => Ticket::query()->where('key', 'COR-8')->first()],
        ]);

        $done = $board->group($rows)[TicketColumn::Done->value];

        $this->assertCount(1, $done);
        $this->assertSame('COR-8', $done->first()['id']);
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

    public function test_stuck_lists_the_oldest_first_and_leaves_parked_tickets_alone(): void
    {
        $board = app(TicketBoard::class);

        $today = Carbon::parse('2026-08-31 09:00');

        // absolute, not relative: subDays on an already-frozen clock stacks the offsets
        Carbon::setTestNow($today->copy()->subDays(9));
        $board->place('COR-9', TicketColumn::Waiting);
        Carbon::setTestNow($today->copy()->subDays(6));
        $board->place('COR-10', TicketColumn::Today);
        Carbon::setTestNow($today->copy()->subDays(30));
        $board->place('COR-11', TicketColumn::Parked);
        Carbon::setTestNow($today);

        $rows = Ticket::query()->get()->map(fn (Ticket $ticket): array => [
            'id' => $ticket->key,
            'column' => $ticket->column,
            'local' => $ticket,
        ]);

        $stuck = $board->stuck(collect($rows));

        $this->assertSame(['COR-9', 'COR-10'], $stuck->pluck('id')->all());

        Carbon::setTestNow();
    }

    public function test_the_board_page_shows_the_columns_and_the_found_list(): void
    {
        app(TicketBoard::class)->create('Eigenes Ticket', column: TicketColumn::Today);

        $this->get(route('tickets'))
            ->assertOk()
            ->assertSee(__('app.ticket.column.today'))
            ->assertSee(__('app.ticket.column.waiting'))
            ->assertSee('TAKT-1')
            ->assertSee('Eigenes Ticket');
    }

    public function test_the_ticket_file_opens_for_a_local_ticket(): void
    {
        $ticket = app(TicketBoard::class)->create('Eigenes Ticket', 'Beschreibung dazu');

        $this->get(route('tickets.show', ['key' => $ticket->key]))
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
