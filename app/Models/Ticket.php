<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The local half of a ticket. Everything here is Takt's own: my estimate,
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
        'user_id', 'key', 'source', 'title', 'body', 'estimate_seconds', 'notes',
        'focused_at', 'ignored_at', 'promoted_url',
    ];

    protected $casts = [
        'estimate_seconds' => 'int',
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
