<?php

declare(strict_types=1);

namespace App\Enums;

enum EntryType: string
{
    case Work = 'work';
    case Break = 'break';

    public function label(): string
    {
        return match ($this) {
            self::Work => __('app.type.work'),
            self::Break => __('app.type.break'),
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::Work => self::Break,
            self::Break => self::Work,
        };
    }
}
