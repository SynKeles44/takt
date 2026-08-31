<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketColumn;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The local half of a ticket. Everything here is Takt's own: the column of my day, my estimate,
 * my notes, the ignore flag. The title and state of a Linear ticket are fetched, not stored —
 * the `title` column only caches the last seen one so a list can render before Linear answers.
 *
 * A local ticket (`source = local`) owns its title outright and exists nowhere else until it is
 * promoted into Linear.
 */
class Ticket extends Model
{
    use BelongsToUser;

    public const string LOCAL_PREFIX = 'TAKT';

    protected $fillable = [
        'user_id', 'key', 'source', 'title', 'body', 'column', 'position',
        'column_changed_at', 'waiting_reason', 'estimate_seconds', 'notes',
        'focused_at', 'ignored_at', 'promoted_url',
    ];

    protected $casts = [
        'column' => TicketColumn::class,
        'position' => 'int',
        'estimate_seconds' => 'int',
        'column_changed_at' => 'datetime',
        'focused_at' => 'datetime',
        'ignored_at' => 'datetime',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('source', 'local');
    }

    public function isLocal(): bool
    {
        return $this->source === 'local';
    }

    public function isIgnored(): bool
    {
        return $this->ignored_at !== null;
    }

    /**
     * Days since the ticket last changed column. The number that says "this is stuck" — and it
     * counts from the move, not from the last commit, because a ticket can sit in Wartet while
     * its branch keeps moving.
     */
    public function daysInColumn(): ?int
    {
        return $this->column_changed_at === null
            ? null
            : (int) $this->column_changed_at->startOfDay()->diffInDays(Carbon::today());
    }

    /** The next free position at the end of a column, so a drop lands where it was dropped. */
    public static function nextPosition(TicketColumn $column): int
    {
        return (int) self::query()->where('column', $column->value)->max('position') + 1;
    }

    /** `TAKT-7` — the number after the highest one taken, never reusing a deleted one. */
    public static function nextLocalKey(): string
    {
        $taken = self::query()
            ->local()
            ->pluck('key')
            ->map(fn (string $key): int => (int) mb_substr($key, mb_strlen(self::LOCAL_PREFIX) + 1))
            ->max();

        return self::LOCAL_PREFIX.'-'.(((int) $taken) + 1);
    }
}
