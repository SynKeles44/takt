<?php

declare(strict_types=1);

namespace App\Enums;

enum DueState: string
{
    case Overdue = 'overdue';
    case Warning = 'warning';
    case Today = 'today';
    case Week = 'week';
    case Later = 'later';
    case Undated = 'undated';
    case Done = 'done';

    public function label(): string
    {
        return __('app.due.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Overdue => 'danger',
            self::Warning => 'rest',
            self::Today => 'accent',
            self::Week => 'info',
            self::Later => 'work',
            self::Undated, self::Done => 'neutral',
        };
    }

    public function pillClasses(): string
    {
        return match ($this->tone()) {
            'danger' => 'border-danger/40 bg-danger/15 text-danger-text',
            'rest' => 'border-rest/40 bg-rest/15 text-rest-text',
            'accent' => 'border-accent/40 bg-accent/15 text-accent-text',
            'info' => 'border-info/40 bg-info/15 text-info-text',
            'work' => 'border-work/40 bg-work/15 text-work-text',
            default => 'border-line bg-raised text-muted',
        };
    }

    /** An inset bar keeps the row's own border intact, unlike a coloured left border. */
    public function accentClass(): string
    {
        return 'row-accent row-accent-'.$this->tone();
    }

    public function dotClass(): string
    {
        return match ($this->tone()) {
            'danger' => 'bg-danger',
            'rest' => 'bg-rest',
            'accent' => 'bg-accent',
            'info' => 'bg-info',
            'work' => 'bg-work',
            default => 'bg-muted',
        };
    }

    public function headingClass(): string
    {
        return match ($this->tone()) {
            'danger' => 'text-danger-text',
            'rest' => 'text-rest-text',
            'accent' => 'text-accent-text',
            'info' => 'text-info-text',
            'work' => 'text-work-text',
            default => 'text-muted',
        };
    }

    public function isUrgent(): bool
    {
        return $this === self::Overdue || $this === self::Warning;
    }

    /** @return list<self> */
    public static function groups(): array
    {
        return [self::Overdue, self::Warning, self::Today, self::Week, self::Later, self::Undated];
    }
}
