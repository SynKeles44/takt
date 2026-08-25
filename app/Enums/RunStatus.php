<?php

declare(strict_types=1);

namespace App\Enums;

enum RunStatus: string
{
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';
    case Stopped = 'stopped';

    public function label(): string
    {
        return __('app.run.'.$this->value);
    }

    public function isOpen(): bool
    {
        return $this === self::Running;
    }

    public function classes(): string
    {
        return match ($this) {
            self::Running => 'border-accent/40 bg-accent/10 text-accent-text',
            self::Finished => 'border-work/40 bg-work/10 text-work-text',
            self::Failed => 'border-danger/40 bg-danger/10 text-danger-text',
            self::Stopped => 'border-line bg-raised text-muted',
        };
    }
}
