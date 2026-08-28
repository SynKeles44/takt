<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A stretch one application was in front. Recorded locally, never sent anywhere. */
class ActivitySpan extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'app', 'title', 'started_at', 'ended_at'];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function scopeOnDay(Builder $query, CarbonInterface $day): Builder
    {
        return $query->whereBetween('started_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
    }

    public function seconds(): int
    {
        return max(0, (int) $this->started_at->diffInSeconds($this->ended_at));
    }
}
