<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EntryType;
use App\Enums\TicketColumn;
use App\Services\TicketBoard;
use App\Services\TimeTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Start the clock for a ticket. This is the whole reason the ticket area is worth having: the
 * time booked against a ticket stops being a guess — an even split of the day — and becomes a
 * measurement.
 *
 * Starting also moves the ticket into Heute and makes it the focus, because that is what
 * starting work on something means; doing it in three separate clicks would be honest and
 * annoying.
 */
class TicketTimerController extends Controller
{
    public function __invoke(Request $request, string $key, TicketBoard $board, TimeTracker $tracker): RedirectResponse
    {
        $running = $tracker->running();
        $ticket = $board->row($key);

        // the same ticket already running means stop, so one button can do both
        if ($running !== null && $running->ticket_id === $ticket->getKey() && $running->type === EntryType::Work) {
            $tracker->stop();

            return back()->with('status', __('app.ticket.timer_stopped', ['id' => $key]));
        }

        $tracker->start(EntryType::Work, null, $ticket);

        if ($ticket->column !== TicketColumn::Today) {
            $board->place($key, TicketColumn::Today);
        }

        $board->focus($key);

        return back()->with('status', __('app.ticket.timer_started', ['id' => $key]));
    }
}
