<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntryType;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class TimeEntry extends Model
{
    use BelongsToUser, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'type',
        'started_at',
        'ended_at',
        'note',
    ];

    protected $casts = [
        'type' => EntryType::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    /** The ticket this booking belongs to, when it belongs to one — most do not. */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    public function scopeOfType(Builder $query, EntryType $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('started_at', '>=', $from)->where('started_at', '<=', $to);
    }

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->between($day->copy()->startOfDay(), $day->copy()->endOfDay());
    }

    public function isRunning(): bool
    {
        return $this->ended_at === null;
    }

    public function durationInSeconds(?CarbonInterface $now = null): int
    {
        $end = $this->ended_at ?? $now ?? Carbon::now();

        return max(0, (int) $this->started_at->diffInSeconds($end, absolute: false));
    }

    public function overlaps(CarbonInterface $start, CarbonInterface $end): bool
    {
        $ownEnd = $this->ended_at ?? Carbon::now();

        return $this->started_at < $end && $ownEnd > $start;
    }
}
