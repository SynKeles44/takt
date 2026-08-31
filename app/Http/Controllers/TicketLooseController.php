<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TicketBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What to do with an id that only the code knows: never show it again, or turn it into a real
 * ticket here. Ignoring is remembered, which is the whole point — the footnote list shrinks as
 * it is used instead of regrowing on every visit.
 */
class TicketLooseController extends Controller
{
    public function __invoke(Request $request, TicketBoard $board): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:32', 'regex:/^[A-Z][A-Z0-9]{1,9}-\d{1,6}$/'],
            'aktion' => ['required', 'in:ignorieren,zurueck'],
        ]);

        if ($data['aktion'] === 'ignorieren') {
            $board->ignore($data['key']);

            return back()->with('status', __('app.ticket.ignored', ['id' => $data['key']]));
        }

        $board->unignore($data['key']);

        return back()->with('status', __('app.ticket.unignored', ['id' => $data['key']]));
    }
}
