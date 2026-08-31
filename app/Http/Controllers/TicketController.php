<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketColumn;
use App\Services\TicketBoard;
use App\Services\Tickets;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The board. Five columns that describe my day — never Linear's workflow — plus the tickets not
 * on the board yet, plus the ids only the code knows as a collapsed footnote.
 */
class TicketController extends Controller
{
    /** How many found-in-the-code ids the footnote renders at once. */
    private const int LOOSE_LIMIT = 40;

    public function __invoke(Request $request, Tickets $tickets, TicketBoard $board): View
    {
        $request->validate([
            'tage' => ['nullable', 'integer', 'min:7', 'max:365'],
            'q' => ['nullable', 'string', 'max:60'],
            'ansicht' => ['nullable', 'in:board,liste'],
        ]);

        $days = (int) ($request->integer('tage') ?: Tickets::DEFAULT_DAYS);
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $view = (string) $request->query('ansicht', 'board');

        $result = $tickets->collect($request->user(), $days);

        $matches = fn (array $row): bool => $term === ''
            || str_contains(mb_strtolower($row['id']), $term)
            || str_contains(mb_strtolower((string) $row['title']), $term);

        $rows = $result['tickets']->filter($matches)->values();
        $loose = $result['loose']->filter($matches)->values();

        return view('tickets', [
            'board' => $board->group($rows),
            'inbox' => $board->inbox($rows),
            'stuck' => $board->stuck($rows),
            'focused' => $board->focused(),
            /*
             * Capped, and the cap is stated in the view. Rendering all of them cost 300 KB of the
             * page in the real account — two forms with a CSRF token each, 158 times, inside a
             * collapsed block nobody had opened yet. The list shrinks as ids are hidden, so the
             * cap stops mattering once it has been used a few times.
             */
            'loose' => $loose->take(self::LOOSE_LIMIT),
            'looseTotal' => $loose->count(),
            'ignored' => $result['ignored'],
            'total' => $result['tickets']->count(),
            'shown' => $rows->count(),
            'calibration' => $tickets->calibration($rows),
            'error' => $result['error'],
            'configured' => $result['configured'],
            'columns' => TicketColumn::board(),
            'days' => $days,
            'term' => (string) $request->query('q', ''),
            'view' => $view,
            'windows' => [30, 90, 180],
        ]);
    }
}
