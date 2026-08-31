<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TicketBoard;
use App\Support\Duration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The local half of a ticket: my notes, my estimate, the reason it is waiting, and for a local
 * ticket its title and description. None of this is ever written to Linear.
 */
class TicketUpdateController extends Controller
{
    public function __invoke(Request $request, string $key, TicketBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'notizen' => ['nullable', 'string', 'max:20000'],
            'schaetzung' => ['nullable', 'string', 'max:12'],
            'grund' => ['nullable', 'string', 'max:120'],
            'titel' => ['nullable', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:20000'],
        ]);

        $ticket = $board->row($key);

        if ($request->has('notizen')) {
            $board->notes($key, (string) ($data['notizen'] ?? ''));
        }

        if ($request->has('schaetzung')) {
            // accepts "1:30" and "90m" and "2h" — the same shapes the rest of the app takes
            $board->estimate($key, Duration::parse((string) ($data['schaetzung'] ?? '')));
        }

        if ($request->has('grund')) {
            $board->waitingReason($key, (string) ($data['grund'] ?? ''));
        }

        if ($ticket->isLocal() && $request->hasAny(['titel', 'beschreibung'])) {
            $ticket->update(array_filter([
                'title' => $data['titel'] ?? null,
                'body' => $data['beschreibung'] ?? null,
            ], static fn (mixed $value): bool => $value !== null));
        }

        return back()->with('status', __('app.ticket.saved'));
    }
}
