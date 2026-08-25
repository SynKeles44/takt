<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AbsenceType;
use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Absence extends Model
{
    use BelongsToUser;

    protected $fillable = ['user_id', 'type', 'starts_on', 'ends_on', 'note'];

    protected $casts = [
        'type' => AbsenceType::class,
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->where('starts_on', '<=', $to->toDateString())
            ->where('ends_on', '>=', $from->toDateString());
    }

    public function workdays(): int
    {
        $days = 0;

        for ($day = $this->starts_on->copy(); $day->lessThanOrEqualTo($this->ends_on); $day->addDay()) {
            if (! $day->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }

    public function covers(CarbonInterface $day): bool
    {
        return $day->betweenIncluded($this->starts_on, $this->ends_on);
    }
}
