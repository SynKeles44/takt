<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ParsedTodoInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class TodoInputParser
{
    private const array WEEKDAYS = [
        'montag' => 1, 'dienstag' => 2, 'mittwoch' => 3, 'donnerstag' => 4,
        'freitag' => 5, 'samstag' => 6, 'sonntag' => 7,
    ];

    public function parse(string $input, ?Carbon $now = null): ParsedTodoInput
    {
        $now ??= Carbon::now();
        $text = ' '.trim($input).' ';

        [$text, $tags] = $this->extractTags($text);
        [$text, $time] = $this->extractTime($text);
        [$text, $date] = $this->extractDate($text, $now);

        $title = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? '');

        if ($title === '') {
            $title = trim($input);
        }

        if ($date === null && $time === null) {
            return new ParsedTodoInput($title, null, false, $tags);
        }

        $due = ($date ?? $now->copy()->startOfDay());

        if ($time !== null) {
            $due = $due->copy()->setTime($time[0], $time[1]);

            if ($date === null && $due->lessThanOrEqualTo($now)) {
                $due->addDay();
            }
        } else {
            $due = $due->copy()->setTime(23, 59);
        }

        return new ParsedTodoInput($title, $due->startOfMinute(), $time !== null, $tags);
    }

    /** @return array{0: string, 1: list<string>} */
    private function extractTags(string $text): array
    {
        $tags = [];

        $text = preg_replace_callback('/#([\p{L}\p{N}_-]{1,60})/u', function (array $match) use (&$tags): string {
            $tags[] = $match[1];

            return ' ';
        }, $text) ?? $text;

        return [$text, array_values(array_unique($tags))];
    }

    /** @return array{0: string, 1: ?array{0: int, 1: int}} */
    private function extractTime(string $text): array
    {
        $patterns = [
            '/\b(?:um\s+)?([01]?\d|2[0-3]):([0-5]\d)\s*(?:uhr)?\b/iu',
            '/\b(?:um\s+)?([01]?\d|2[0-3])\s*uhr\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                $text = str_replace($match[0], ' ', $text);

                return [$text, [(int) $match[1], (int) ($match[2] ?? 0)]];
            }
        }

        return [$text, null];
    }

    /** @return array{0: string, 1: ?Carbon} */
    private function extractDate(string $text, Carbon $now): array
    {
        $relative = [
            'übermorgen' => 2,
            'uebermorgen' => 2,
            'morgen' => 1,
            'heute' => 0,
        ];

        foreach ($relative as $word => $offset) {
            if (preg_match('/\b'.$word.'\b/iu', $text, $match) === 1) {
                return [str_replace($match[0], ' ', $text), $now->copy()->startOfDay()->addDays($offset)];
            }
        }

        if (preg_match('/\bin\s+(\d{1,3})\s+tag(?:en)?\b/iu', $text, $match) === 1) {
            return [str_replace($match[0], ' ', $text), $now->copy()->startOfDay()->addDays((int) $match[1])];
        }

        if (preg_match('/\bin\s+(?:einer|1)\s+woche\b/iu', $text, $match) === 1) {
            return [str_replace($match[0], ' ', $text), $now->copy()->startOfDay()->addWeek()];
        }

        if (preg_match('/\b(?:am\s+)?(\d{1,2})\.(\d{1,2})\.(\d{4})?(?!\d)/u', $text, $match) === 1) {
            $year = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : $now->year;
            $date = Carbon::createFromDate($year, (int) $match[2], (int) $match[1])->startOfDay();

            if (! isset($match[3]) || $match[3] === '') {
                if ($date->lessThan($now->copy()->startOfDay())) {
                    $date->addYear();
                }
            }

            return [str_replace($match[0], ' ', $text), $date];
        }

        foreach (self::WEEKDAYS as $name => $iso) {
            if (preg_match('/\b(?:am\s+)?'.$name.'\b/iu', $text, $match) === 1) {
                $date = $now->copy()->startOfDay();

                do {
                    $date->addDay();
                } while ($date->isoWeekday() !== $iso);

                return [str_replace($match[0], ' ', $text), $date];
            }
        }

        return [$text, null];
    }

    /** @param list<string> $names */
    public function matchTagIds(array $names, iterable $tags): array
    {
        $wanted = array_map(fn (string $name): string => Str::lower($name), $names);
        $ids = [];

        foreach ($tags as $tag) {
            if (in_array(Str::lower($tag->name), $wanted, true)) {
                $ids[] = $tag->getKey();
            }
        }

        return $ids;
    }
}
