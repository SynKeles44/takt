<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Support\Duration;
use Illuminate\Support\Collection;

final class WorkTimeCompliance
{
    private const int SIX_HOURS = 21_600;

    private const int NINE_HOURS = 32_400;

    private const int TEN_HOURS = 36_000;

    private const int BREAK_AFTER_SIX = 1_800;

    private const int BREAK_AFTER_NINE = 2_700;

    private const int REST_PERIOD = 39_600;

    /**
     * @param  Collection<int, TimeEntry>  $entries  the day's entries
     * @return list<array{key: string, text: string, level: string}>
     */
    public function check(Collection $entries, ?TimeEntry $previousEnd = null): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $work = (int) $entries->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());
        $break = (int) $entries->where('type', EntryType::Break)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

        $hints = [];

        if ($work > self::NINE_HOURS && $break < self::BREAK_AFTER_NINE) {
            $hints[] = $this->hint('break_45', 'warning', ['duration' => Duration::human(self::BREAK_AFTER_NINE - $break)]);
        } elseif ($work > self::SIX_HOURS && $break < self::BREAK_AFTER_SIX) {
            $hints[] = $this->hint('break_30', 'warning', ['duration' => Duration::human(self::BREAK_AFTER_SIX - $break)]);
        }

        if ($work > self::TEN_HOURS) {
            $hints[] = $this->hint('max_ten', 'danger', ['duration' => Duration::human($work - self::TEN_HOURS)]);
        }

        if ($previousEnd?->ended_at !== null) {
            $rest = (int) $previousEnd->ended_at->diffInSeconds($entries->sortBy('started_at')->first()->started_at, absolute: false);

            if ($rest > 0 && $rest < self::REST_PERIOD) {
                $hints[] = $this->hint('rest', 'warning', ['duration' => Duration::human($rest)]);
            }
        }

        return $hints;
    }

    private function hint(string $key, string $level, array $replace = []): array
    {
        return [
            'key' => $key,
            'level' => $level,
            'text' => __('app.compliance.'.$key, $replace),
        ];
    }
}
