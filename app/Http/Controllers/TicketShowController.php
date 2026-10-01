<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TicketFile;
use App\Support\Deferred;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The ticket file — the page that stops this area from being a list.
 */
class TicketShowController extends Controller
{
    public function __invoke(Request $request, string $key, TicketFile $file): View
    {
        /*
         * Linear, GitHub and git across every registered project, all before the first byte — a
         * second on a cold cache. The page's shape is known without any of it, so the first
         * request renders that and the browser asks again for the contents.
         */
        $defer = Deferred::wanted($request);

        return view('ticket', [
            'defer' => $defer,
            'file' => $defer ? null : $file->for($request->user(), $key),
            'key' => $key,
        ]);
    }
}
