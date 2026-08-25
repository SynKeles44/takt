<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Carbon;

final readonly class Period
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    public static function fromDateAndTimes(string $date, string $start, string $end): self
    {
        $startsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$start)->startOfMinute();
        $endsAt = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$end)->startOfMinute();

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt->addDay();
        }

        return new self($startsAt, $endsAt);
    }

    public function overlaps(self $other): bool
    {
        return $this->start->lessThan($other->end) && $this->end->greaterThan($other->start);
    }

    public function contains(self $other): bool
    {
        return $other->start->greaterThanOrEqualTo($this->start)
            && $other->end->lessThanOrEqualTo($this->end);
    }

    public function seconds(): int
    {
        return max(0, (int) $this->start->diffInSeconds($this->end, absolute: false));
    }
}
