<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NetworkAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NetworkAccessController extends Controller
{
    public function __invoke(Request $request, NetworkAccess $network): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);

        $data['enabled'] ? $network->enable() : $network->disable();

        return back()->with('status', __($data['enabled'] ? 'app.network.on_done' : 'app.network.off_done'));
    }
}
