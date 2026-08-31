<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketColumn;
use App\Services\TicketBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A ticket that exists only here. For work that is real but not team-visible yet — an idea, a
 * chore, a "look at this before it becomes a ticket". It behaves like a Linear ticket everywhere
 * and can be promoted into Linear later.
 */
class TicketStoreController extends Controller
{
    public function __invoke(Request $request, TicketBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'titel' => ['required', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:5000'],
            'spalte' => ['nullable', 'string', 'in:'.implode(',', array_column(TicketColumn::cases(), 'value'))],
        ]);

        $ticket = $board->create(
            $data['titel'],
            filled($data['beschreibung'] ?? null) ? (string) $data['beschreibung'] : null,
            filled($data['spalte'] ?? null) ? TicketColumn::from((string) $data['spalte']) : TicketColumn::Next,
        );

        return redirect()
            ->route('tickets.show', ['key' => $ticket->key])
            ->with('status', __('app.ticket.created', ['id' => $ticket->key]));
    }
}
