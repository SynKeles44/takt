<?php

declare(strict_types=1);

namespace App\Enums;

use Illuminate\Support\Carbon;

enum Recurrence: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekdays = 'weekdays';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return __('app.recurrence.'.$this->value);
    }

    public function repeats(): bool
    {
        return $this !== self::None;
    }

    public function next(Carbon $from, ?Carbon $after = null): ?Carbon
    {
        if (! $this->repeats()) {
            return null;
        }

        $after ??= Carbon::now();
        $next = $from->copy();
        $guard = 0;

        do {
            $next = $this->advance($next);
        } while ($next->lessThanOrEqualTo($after) && ++$guard < 500);

        return $next;
    }

    private function advance(Carbon $date): Carbon
    {
        return match ($this) {
            self::Daily => $date->addDay(),
            self::Weekdays => $date->addWeekday(),
            self::Weekly => $date->addWeek(),
            self::Biweekly => $date->addWeeks(2),
            self::Monthly => $date->addMonthNoOverflow(),
            self::Yearly => $date->addYearNoOverflow(),
            self::None => $date,
        };
    }
}
