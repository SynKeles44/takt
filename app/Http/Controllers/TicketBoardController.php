<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketColumn;
use App\Services\TicketBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Moving a ticket between the columns of my day. The key is the identity, not a database id: a
 * Linear ticket has no local row until it is first placed somewhere.
 */
class TicketBoardController extends Controller
{
    public function __invoke(Request $request, TicketBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9]{1,9}-\d{1,6}$/'],
            'spalte' => ['nullable', 'string', 'in:'.implode(',', array_column(TicketColumn::cases(), 'value'))],
            'grund' => ['nullable', 'string', 'max:120'],
        ]);

        $column = filled($data['spalte'] ?? null) ? TicketColumn::from((string) $data['spalte']) : null;

        $board->place($data['key'], $column);

        if ($column === TicketColumn::Waiting && filled($data['grund'] ?? null)) {
            $board->waitingReason($data['key'], (string) $data['grund']);
        }

        return back()->with('status', __('app.ticket.moved', [
            'id' => $data['key'],
            'column' => $column?->label() ?? __('app.ticket.column.none'),
        ]));
    }
}
