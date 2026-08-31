<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketColumn;
use App\Services\TicketFile;
use Illuminate\View\View;

/**
 * The ticket file — the page that stops this area from being a list.
 */
class TicketShowController extends Controller
{
    public function __invoke(string $key, TicketFile $file): View
    {
        return view('ticket', [
            'file' => $file->for(auth()->user(), $key),
            'columns' => TicketColumn::board(),
        ]);
    }
}
