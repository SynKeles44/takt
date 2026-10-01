<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Support\Carbon;

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

    /** Hidden from the board for good — an id that is not mine to look at. */
    public function ignore(string $key): Ticket
    {
        $ticket = $this->row($key, 'git');

        $ticket->update(['ignored_at' => Carbon::now()]);

        return $ticket;
    }

    public function unignore(string $key): Ticket
    {
        $ticket = $this->row($key, 'git');

        $ticket->update(['ignored_at' => null]);

        return $ticket;
    }

    /** A ticket that exists only here, with the next free local key. */
    public function create(string $title, ?string $body = null): Ticket
    {
        $key = Ticket::nextLocalKey();

        return Ticket::query()->create([
            'key' => $key,
            'source' => 'local',
            'title' => $title,
            'body' => $body,
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
}
