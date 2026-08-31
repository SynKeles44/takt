<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TicketBoard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One ticket at a time is the current focus. The menu bar reads it, so this is the answer to
 * "what am I on right now" without opening the window.
 */
class TicketFocusController extends Controller
{
    public function __invoke(Request $request, string $key, TicketBoard $board): RedirectResponse
    {
        $focused = $board->focused();
        $same = $focused?->key === $key;

        $board->focus($same ? null : $key);

        return back()->with('status', $same
            ? __('app.ticket.unfocused')
            : __('app.ticket.focused', ['id' => $key]));
    }
}
