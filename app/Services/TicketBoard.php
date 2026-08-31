<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketColumn;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every write to the local ticket layer goes through here.
 *
 * The key — `COR-6950`, `TAKT-3` — is the identity everywhere, never the database id: a Linear
 * ticket has no row here until something local is said about it, and a caller should not have to
 * know whether the row already exists. So each operation reaches the row through firstOrCreate
 * and an untouched account stays empty.
 */
final class TicketBoard
{
    public function row(string $key, string $source = 'linear'): Ticket
    {
        return Ticket::query()->firstOrCreate(['key' => $key], ['source' => $source]);
    }

    /**
     * Move a ticket into a column. Position is appended unless one is given, and the timestamp
     * only moves when the column actually changes — otherwise reordering inside a column would
     * reset the "how long has this been stuck" figure the board exists to show.
     */
    public function place(string $key, ?TicketColumn $column, ?int $position = null): Ticket
    {
        $ticket = $this->row($key);
        $moved = $ticket->column !== $column;

        $ticket->fill([
            'column' => $column,
            'position' => $position ?? ($column === null ? 0 : Ticket::nextPosition($column)),
        ]);

        if ($moved) {
            $ticket->column_changed_at = $column === null ? null : Carbon::now();

            // a reason belongs to one stay in Wartet, not to the ticket forever
            if ($column !== TicketColumn::Waiting) {
                $ticket->waiting_reason = null;
            }
        }

        $ticket->save();

        return $ticket;
    }

    public function waitingReason(string $key, ?string $reason): Ticket
    {
        $ticket = $this->row($key);

        $ticket->update(['waiting_reason' => $reason === '' ? null : $reason]);

        return $ticket;
    }

    /**
     * An id found in the code that is not a ticket and never will be. Remembered so the footnote
     * list shrinks as it is used instead of regrowing on every visit.
     */
    public function ignore(string $key): Ticket
    {
        $ticket = $this->row($key, 'git');

        $ticket->update(['ignored_at' => Carbon::now(), 'column' => null]);

        return $ticket;
    }

    public function unignore(string $key): Ticket
    {
        $ticket = $this->row($key, 'git');

        $ticket->update(['ignored_at' => null]);

        return $ticket;
    }

    /** A ticket that exists only here, with the next free local key. */
    public function create(string $title, ?string $body = null, ?TicketColumn $column = null): Ticket
    {
        $key = Ticket::nextLocalKey();

        return Ticket::query()->create([
            'key' => $key,
            'source' => 'local',
            'title' => $title,
            'body' => $body,
            'column' => $column,
            'position' => $column === null ? 0 : Ticket::nextPosition($column),
            'column_changed_at' => $column === null ? null : Carbon::now(),
        ]);
    }

    public function notes(string $key, ?string $notes): Ticket
    {
        $ticket = $this->row($key);

        $ticket->update(['notes' => $notes === '' ? null : $notes]);

        return $ticket;
    }

    public function estimate(string $key, ?int $seconds): Ticket
    {
        $ticket = $this->row($key);

        $ticket->update(['estimate_seconds' => $seconds !== null && $seconds > 0 ? $seconds : null]);

        return $ticket;
    }

    /** Exactly one ticket is the current focus; setting a new one clears the old. */
    public function focus(?string $key): ?Ticket
    {
        Ticket::query()->whereNotNull('focused_at')->update(['focused_at' => null]);

        if ($key === null) {
            return null;
        }

        $ticket = $this->row($key);

        $ticket->update(['focused_at' => Carbon::now()]);

        return $ticket;
    }

    public function focused(): ?Ticket
    {
        return Ticket::query()->whereNotNull('focused_at')->orderByDesc('focused_at')->first();
    }

    /**
     * The board, grouped by column and sorted by my own order. Finished tickets drop off after a
     * week so the column does not become an archive.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, Collection<int, array<string, mixed>>>
     */
    public function group(Collection $rows): array
    {
        $board = [];

        foreach (TicketColumn::board() as $column) {
            $board[$column->value] = $rows
                ->filter(fn (array $row): bool => $row['column'] === $column)
                ->reject(fn (array $row): bool => $column === TicketColumn::Done
                    && $row['local']?->column_changed_at?->lt(Carbon::now()->subDays(TicketColumn::DONE_DAYS)) === true)
                ->sortBy(fn (array $row): int => $row['local']?->position ?? 0)
                ->values();
        }

        return $board;
    }

    /**
     * Tickets that are not on the board at all — the inbox. Kept separate from the columns so
     * putting something on the board stays a decision rather than a default.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    public function inbox(Collection $rows): Collection
    {
        return $rows->filter(fn (array $row): bool => $row['column'] === null)->values();
    }

    /** Everything untouched for longer than the given days, worst first. */
    public function stuck(Collection $rows, int $days = 5): Collection
    {
        return $rows
            ->filter(fn (array $row): bool => in_array(
                $row['column'],
                [TicketColumn::Today, TicketColumn::Next, TicketColumn::Waiting],
                true,
            ))
            ->filter(fn (array $row): bool => ($row['local']?->daysInColumn() ?? 0) >= $days)
            ->sortByDesc(fn (array $row): int => $row['local']?->daysInColumn() ?? 0)
            ->values();
    }
}
