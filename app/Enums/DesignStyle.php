<?php

declare(strict_types=1);

namespace App\Enums;

enum DesignStyle: string
{
    case Soft = 'soft';
    case Minimal = 'minimal';
    case Bento = 'bento';
    case Glassmorphism = 'glassmorphism';
    case Neumorphism = 'neumorphism';
    case Skeuomorphism = 'skeuomorphism';
    case Industrial = 'industrial';
    case Brutalist = 'brutalist';
    case Terminal = 'terminal';
    case Compact = 'compact';

    public function label(): string
    {
        return __('app.style.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('app.style.'.$this->value.'.description');
    }
}
