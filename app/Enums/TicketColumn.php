<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The columns describe my day, deliberately not Linear's workflow states. Linear's state travels
 * along as a pill on the card so the two are visible side by side — and disagree visibly.
 */
enum TicketColumn: string
{
    case Today = 'today';
    case Next = 'next';
    case Waiting = 'waiting';
    case Parked = 'parked';
    case Done = 'done';

    /** How long a finished ticket stays on the board before it drops off. */
    public const int DONE_DAYS = 7;

    public function label(): string
    {
        return __('app.ticket.column.'.$this->value);
    }

    public function hint(): string
    {
        return __('app.ticket.hint.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::Today => 'play',
            self::Next => 'list-check',
            self::Waiting => 'clock',
            self::Parked => 'pause',
            self::Done => 'check',
        };
    }

    public function accent(): string
    {
        return match ($this) {
            self::Today => 'var(--color-work)',
            self::Next => 'var(--color-accent)',
            self::Waiting => 'var(--color-rest)',
            self::Parked => 'var(--color-muted)',
            self::Done => 'var(--color-work)',
        };
    }

    /** @return list<self> */
    public static function board(): array
    {
        return self::cases();
    }
}
