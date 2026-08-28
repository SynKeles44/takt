<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A stretch the Mac was locked or asleep while a timer kept running. Recorded so the time can
 * be corrected afterwards instead of silently counting as work.
 */
class AwayGap extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'started_at', 'ended_at', 'resolved_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function seconds(): int
    {
        // diffInSeconds returns a float in this Carbon version
        return max(0, (int) $this->started_at->diffInSeconds($this->ended_at));
    }
}
