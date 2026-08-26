<?php

declare(strict_types=1);

namespace App\Enums;

enum AbsenceType: string
{
    case Vacation = 'vacation';
    case Sick = 'sick';
    case Holiday = 'holiday';
    case HomeOffice = 'home_office';
    case Other = 'other';

    public function label(): string
    {
        return __('app.absence.'.$this->value);
    }

    public function pillClasses(): string
    {
        return match ($this) {
            self::Vacation => 'border-accent/30 bg-accent/10 text-accent-text',
            self::Sick => 'border-danger/30 bg-danger/10 text-danger-text',
            self::Holiday => 'border-work/30 bg-work/10 text-work-text',
            self::HomeOffice => 'border-rest/30 bg-rest/10 text-rest-text',
            self::Other => 'border-line bg-raised text-muted',
        };
    }

    /**
     * Whether the day loses its target. Home office is a marker on a normal working day:
     * the hours are worked, so counting it as exempt would turn every one of them into
     * overtime.
     */
    public function blocksWork(): bool
    {
        return $this !== self::HomeOffice;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vacation => 'calendar',
            self::Sick => 'alert',
            self::Holiday => 'calendar-days',
            self::HomeOffice => 'home',
            self::Other => 'tag',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Vacation => 'accent',
            self::Sick => 'danger',
            self::Holiday => 'work',
            self::HomeOffice => 'rest',
            self::Other => 'neutral',
        };
    }
}
