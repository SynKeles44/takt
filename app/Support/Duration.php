<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Number;

final class Duration
{
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
