<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Linear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TicketRefreshController extends Controller
{
    public function __invoke(Request $request, Linear $linear): RedirectResponse
    {
        $linear->forget($request->user());

        return back()->with('status', __('app.tickets.refreshed'));
    }
}
