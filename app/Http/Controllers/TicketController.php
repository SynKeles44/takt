<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TicketColumn;
use App\Services\Linear;
use App\Services\Sprints;
use App\Services\TicketBoard;
use App\Services\Tickets;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The board. Five columns that describe my day — never Linear's workflow — plus the tickets not
 * on the board yet, plus the ids only the code knows as a collapsed footnote.
 */
class TicketController extends Controller
{
    /** How many found-in-the-code ids the footnote renders at once. */
    private const int LOOSE_LIMIT = 40;

    public function __invoke(Request $request, Tickets $tickets, TicketBoard $board, Sprints $sprints, Linear $linear): View
    {
        $request->validate([
            'tage' => ['nullable', 'integer', 'min:7', 'max:365'],
            'q' => ['nullable', 'string', 'max:60'],
            'ansicht' => ['nullable', 'in:board,liste,sprints'],
            'sprint' => ['nullable', 'string', 'max:80'],
            'projekt' => ['nullable', 'string', 'max:80'],
            'label' => ['nullable', 'string', 'max:80'],
        ]);

        $days = (int) ($request->integer('tage') ?: Tickets::DEFAULT_DAYS);
        $term = mb_strtolower(trim((string) $request->query('q', '')));
        $view = (string) $request->query('ansicht', 'board');

        $result = $tickets->collect($request->user(), $days);

        $matches = fn (array $row): bool => $term === ''
            || str_contains(mb_strtolower($row['id']), $term)
            || str_contains(mb_strtolower((string) $row['title']), $term);

        /*
         * The options come from the tickets themselves rather than from Linear. With eighty-eight
         * assigned tickets a filter is what makes the board usable at all, and asking Linear for
         * every project and label of every team would offer values that match nothing here.
         */
        $facets = [
            'sprint' => $this->facet($result['tickets'], fn (array $row): ?string => ($row['cycle']['name'] ?? null)
                ?: (($row['cycle']['number'] ?? null) !== null ? __('app.sprint.number', ['number' => $row['cycle']['number']]) : null)),
            'projekt' => $this->facet($result['tickets'], fn (array $row): ?string => $row['project'] ?? null),
            'label' => $this->facet($result['tickets'], fn (array $row): array => array_map(
                static fn (array $label): string => $label['name'],
                $row['labels'] ?? [],
            )),
        ];

        $picked = [
            'sprint' => (string) $request->query('sprint', ''),
            'projekt' => (string) $request->query('projekt', ''),
            'label' => (string) $request->query('label', ''),
        ];

        $keeps = fn (array $row): bool => $matches($row)
            && ($picked['sprint'] === '' || in_array($picked['sprint'], (array) $facets['sprint']['of']($row), true))
            && ($picked['projekt'] === '' || in_array($picked['projekt'], (array) $facets['projekt']['of']($row), true))
            && ($picked['label'] === '' || in_array($picked['label'], (array) $facets['label']['of']($row), true));

        $rows = $result['tickets']->filter($keeps)->values();
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
            'facets' => array_map(static fn (array $facet): array => $facet['values'], $facets),
            'picked' => $picked,
            // the workflow per team, so a card can offer Linear's own states without asking per card
            'states' => $view === 'sprints' ? [] : $linear->statesForTeams(
                $request->user(),
                $rows->pluck('id')->all(),
            ),
            // only the sprint view pays for the sprint read; it answers from the same cached issues
            'sprints' => $view === 'sprints' ? $sprints->recent($request->user()) : null,
        ]);
    }

    /**
     * One filter: the values present on the tickets, and the reader that says which of them a
     * ticket carries. A ticket has one sprint and one project but any number of labels, so the
     * reader always answers with a list and the caller never has to know which kind it got.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{values: list<string>, of: \Closure}
     */
    private function facet(Collection $rows, \Closure $read): array
    {
        $of = static fn (array $row): array => array_values(array_filter((array) $read($row)));

        return [
            'values' => $rows->flatMap($of)->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'of' => $of,
        ];
    }
}
