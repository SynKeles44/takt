<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Linear;
use App\Services\TicketBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Everything that goes back to Linear, and nothing else does: title, description, state,
 * priority, a comment, assignment — plus promoting a local ticket into a real issue.
 *
 * A failed write keeps the local value and says so. Silently losing an edit is worse than a
 * visible conflict.
 */
class TicketLinearController extends Controller
{
    public function __invoke(Request $request, string $key, Linear $linear, TicketBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'aktion' => ['required', 'in:felder,kommentar,zuweisen,abgeben,anlegen'],
            'titel' => ['nullable', 'string', 'max:200'],
            'beschreibung' => ['nullable', 'string', 'max:20000'],
            'status' => ['nullable', 'string', 'max:60'],
            'prio' => ['nullable', 'integer', 'min:0', 'max:4'],
            'kommentar' => ['nullable', 'string', 'max:10000'],
        ]);

        $user = $request->user();

        if ($data['aktion'] === 'anlegen') {
            return $this->promote($key, $linear, $board);
        }

        $result = match ($data['aktion']) {
            'kommentar' => filled($data['kommentar'] ?? null)
                ? $linear->comment($user, $key, (string) $data['kommentar'])
                : ['ok' => true, 'error' => null],
            'zuweisen' => $linear->update($user, $key, ['assignToMe' => true]),
            'abgeben' => $linear->update($user, $key, ['assignToMe' => false]),
            default => $linear->update($user, $key, array_filter([
                'title' => $data['titel'] ?? null,
                'description' => $data['beschreibung'] ?? null,
                'state' => $data['status'] ?? null,
                'priority' => $data['prio'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '')),
        };

        return back()->with('status', $result['error'] ?? ($result['ok']
            ? __('app.ticket.linear_saved')
            : __('app.ticket.linear_failed')));
    }

    /** A local ticket graduates: it becomes a Linear issue and keeps its notes and its time. */
    private function promote(string $key, Linear $linear, TicketBoard $board): RedirectResponse
    {
        $ticket = $board->row($key, 'local');

        if (! $ticket->isLocal()) {
            return back()->with('status', __('app.ticket.already_linear'));
        }

        $result = $linear->create(auth()->user(), (string) $ticket->title, $ticket->body);

        if (! $result['ok'] || $result['identifier'] === null) {
            return back()->with('status', $result['error'] ?? __('app.ticket.linear_failed'));
        }

        /*
         * The key changes, and that is the point: from here on this row is the local half of a
         * Linear ticket, carrying its notes, estimate, column and booked time across. The old
         * key is remembered nowhere, because nothing outside this table used it.
         */
        $ticket->update([
            'key' => $result['identifier'],
            'source' => 'linear',
            'promoted_url' => $result['url'],
        ]);

        return redirect()
            ->route('tickets.show', ['key' => $result['identifier']])
            ->with('status', __('app.ticket.promoted', ['id' => $result['identifier']]));
    }
}
