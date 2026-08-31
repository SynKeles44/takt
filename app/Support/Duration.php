<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;

final class Duration
{
    /**
     * Reads a duration the way a person types one: `1:30`, `90m`, `2h`, `2,5h`, or a bare `4`
     * meaning four hours — because an estimate is thought of in hours, not minutes. Anything
     * unparseable is null rather than zero: a rejected input must not silently become "no time".
     */
    public static function parse(string $input): ?int
    {
        $value = mb_strtolower(trim(str_replace(',', '.', $input)));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,3}):([0-5]?\d)$/', $value, $m) === 1) {
            return ((int) $m[1] * 3600) + ((int) $m[2] * 60);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*h$/', $value, $m) === 1) {
            return (int) round(((float) $m[1]) * 3600);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*m(?:in)?$/', $value, $m) === 1) {
            return (int) round(((float) $m[1]) * 60);
        }

        if (preg_match('/^(\d+(?:\.\d+)?)$/', $value, $m) === 1) {
            return (int) round(((float) $m[1]) * 3600);
        }

        return null;
    }

    public static function human(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return sprintf('%dm', $minutes);
        }

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    /**
     * Short form for tight surfaces: "8h", "8h30", "45m".
     */
    public static function compact(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours === 0) {
            return sprintf('%dm', $minutes);
        }

        return $minutes === 0 ? sprintf('%dh', $hours) : sprintf('%dh%02d', $hours, $minutes);
    }

    public static function clock(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf(
            '%02d:%02d:%02d',
            intdiv($seconds, 3600),
            intdiv($seconds % 3600, 60),
            $seconds % 60,
        );
    }

    public static function signed(int $seconds): string
    {
        if ($seconds === 0) {
            return "\u{00b1}0m";
        }

        return ($seconds > 0 ? '+' : "\u{2212}").self::human(abs($seconds));
    }

    public static function signedDecimal(int $seconds): string
    {
        $value = Number::format(self::decimalHours(abs($seconds)), precision: 2);

        return match (true) {
            $seconds > 0 => '+'.$value,
            $seconds < 0 => "\u{2212}".$value,
            default => "\u{00b1}".$value,
        };
    }

    public static function decimalHours(int $seconds): float
    {
        return round(max(0, $seconds) / 3600, 2);
    }
}
