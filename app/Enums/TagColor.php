<?php

declare(strict_types=1);

namespace App\Enums;

enum TagColor: string
{
    case Accent = 'accent';
    case Work = 'work';
    case Rest = 'rest';
    case Danger = 'danger';
    case Neutral = 'neutral';

    public function classes(): string
    {
        return match ($this) {
            self::Accent => 'border-accent/30 bg-accent/10 text-accent-text',
            self::Work => 'border-work/30 bg-work/10 text-work-text',
            self::Rest => 'border-rest/30 bg-rest/10 text-rest-text',
            self::Danger => 'border-danger/30 bg-danger/10 text-danger-text',
            self::Neutral => 'border-line bg-raised text-muted',
        };
    }

    public function dot(): string
    {
        return match ($this) {
            self::Accent => 'bg-accent',
            self::Work => 'bg-work',
            self::Rest => 'bg-rest',
            self::Danger => 'bg-danger',
            self::Neutral => 'bg-muted',
        };
    }

    public function label(): string
    {
        return __('app.tag_color.'.$this->value);
    }
}
