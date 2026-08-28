<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Tickets;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function __invoke(Request $request, Tickets $tickets): View
    {
        $request->validate([
            'tage' => ['nullable', 'integer', 'min:7', 'max:365'],
            'q' => ['nullable', 'string', 'max:60'],
            'status' => ['nullable', 'in:offen,alle'],
        ]);

        $days = (int) ($request->integer('tage') ?: Tickets::DEFAULT_DAYS);
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $status = (string) $request->query('status', 'offen');

        $result = $tickets->collect($request->user(), $days);

        $shown = $result['tickets']
            ->when($status === 'offen', fn ($rows) => $rows->reject(
                fn (array $row): bool => in_array($row['state_type'], ['completed', 'canceled'], true),
            ))
            ->when($term !== '', fn ($rows) => $rows->filter(
                fn (array $row): bool => str_contains(mb_strtolower($row['id']), $term)
                    || str_contains(mb_strtolower((string) $row['title']), $term),
            ))
            ->values();

        return view('tickets', [
            'tickets' => $shown,
            'total' => $result['tickets']->count(),
            'error' => $result['error'],
            'configured' => $result['configured'],
            'days' => $days,
            'term' => (string) $request->query('q', ''),
            'status' => $status,
            'windows' => [30, 90, 180],
        ]);
    }
}
