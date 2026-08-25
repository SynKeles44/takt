<?php

declare(strict_types=1);

namespace App\Enums;

enum Theme: string
{
    case Midnight = 'midnight';
    case Daylight = 'daylight';
    case Onyx = 'onyx';
    case Sage = 'sage';
    case Auto = 'auto';

    public function label(): string
    {
        return __('app.theme.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('app.theme.'.$this->value.'.description');
    }

    public function isAutomatic(): bool
    {
        return $this === self::Auto;
    }

    public function resolved(): self
    {
        return $this === self::Auto ? self::Midnight : $this;
    }

    /** @return array{canvas: string, surface: string, accent: string, ink: string} */
    public function preview(): array
    {
        return match ($this) {
            self::Midnight => ['canvas' => '#060911', 'surface' => '#161c2c', 'accent' => '#6366f1', 'ink' => '#e2e8f0'],
            self::Daylight => ['canvas' => '#f4f6fb', 'surface' => '#ffffff', 'accent' => '#4f46e5', 'ink' => '#1e293b'],
            self::Onyx => ['canvas' => '#000000', 'surface' => '#121214', 'accent' => '#e4e4e7', 'ink' => '#fafafa'],
            self::Sage => ['canvas' => '#2f3a34', 'surface' => '#0f1613', 'accent' => '#8fe39a', 'ink' => '#eef4ef'],
            self::Auto => ['canvas' => '#060911', 'surface' => '#161c2c', 'accent' => '#6366f1', 'ink' => '#e2e8f0'],
        };
    }
}
