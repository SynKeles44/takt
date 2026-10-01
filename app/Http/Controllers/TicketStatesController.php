<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Which workflow states the board draws.
 *
 * Nothing is validated against Linear's own list on purpose: a workflow can gain and lose states,
 * and a stored name that no longer exists simply matches no column rather than failing a save.
 * Storing null for "all of them" is what keeps a new state visible the day it is added — an
 * allow-list of names would quietly hide it.
 */
class TicketStatesController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'states' => ['nullable', 'array', 'max:40'],
            'states.*' => ['string', 'max:80'],
        ]);

        $picked = array_values(array_unique($data['states'] ?? []));

        $request->user()->forceFill([
            'board_states' => $picked === [] ? null : $picked,
        ])->save();

        return back()->with('status', __('app.ticket.states_saved'));
    }
}
