<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Random\Randomizer;

final class WorkHistoryGenerator
{
    private const int STEP = 300;

    private const int DAY_MIN = 22_500;

    private const int DAY_MAX = 35_100;

    private const int EARLIEST_START = 28_800;

    private const int LATEST_START = 34_200;

    private const int EARLIEST_END = 57_600;

    private const int LATEST_END = 68_400;

    private const int BREAK_MIN = 1_800;

    private const int BREAK_MAX = 5_400;

    private const array NOTES = [
        'Sprint-Planung', 'Code Review', 'API Refactoring', 'Kundentermin', 'Bugfixing',
        'Deployment', 'Doku', 'Pairing', 'Retro', 'Support-Tickets', 'Datenmigration',
        'Konzept Zeiterfassung', 'Testing', 'Onboarding', 'Release-Vorbereitung',
    ];

    /** @param  list<string>  $exemptDates public holidays and absences, in Y-m-d */
    public function __construct(
        private readonly Randomizer $randomizer,
        private readonly int $dailyTargetSeconds,
        private readonly array $exemptDates = [],
    ) {}

    public function generate(CarbonInterface $from, CarbonInterface $to, int $balanceSeconds): Collection
    {
        $weeks = $this->workdays($from, $to);

        if ($weeks->isEmpty()) {
            return collect();
        }

        $targets = $this->weeklyTargets($weeks, $balanceSeconds);

        return $weeks
            ->flatMap(fn (Collection $days, string $week): Collection => $days
                ->values()
                ->map(fn (Carbon $day, int $index): array => [
                    'day' => $day,
                    'work' => $targets[$week][$index],
                ]))
            ->flatMap(fn (array $plan): array => $this->blocksForDay($plan['day'], $plan['work']))
            ->values();
    }

    private function workdays(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $days = collect();

        for ($day = Carbon::instance($from->toDateTimeImmutable())->startOfDay(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            /*
             * A public holiday or an absence carries no target, so booking work on it turns
             * straight into overtime — which is right for real work, and wrong for generated
             * history: it left the balance permanently in the plus.
             */
            if ($day->isWeekend() || in_array($day->toDateString(), $this->exemptDates, true)) {
                continue;
            }

            $days->push($day->copy());
        }

        return $days->groupBy(fn (Carbon $day): string => $day->isoFormat('GGGG-WW'));
    }

    private function weeklyTargets(Collection $weeks, int $balanceSeconds): array
    {
        $keys = $weeks->keys()->all();
        $remainingBalance = $balanceSeconds;
        $targets = [];

        foreach (array_reverse($keys) as $week) {
            $count = $weeks[$week]->count();
            $base = $count * $this->dailyTargetSeconds;
            $room = $count * self::DAY_MAX - $base;
            $floor = $count * self::DAY_MIN - $base;

            $share = max($floor, min($room, $remainingBalance));
            $remainingBalance -= $share;

            $targets[$week] = $this->distribute($base + $share, $count);
        }

        return $targets;
    }

    private function distribute(int $total, int $count): array
    {
        $share = intdiv(intdiv($total, $count), self::STEP) * self::STEP;
        $days = array_fill(0, $count, $share);
        $days[$count - 1] += $total - $share * $count;

        for ($i = 0; $i < $count * 12; $i++) {
            $from = $this->randomizer->getInt(0, $count - 1);
            $to = $this->randomizer->getInt(0, $count - 1);

            if ($from === $to) {
                continue;
            }

            $delta = $this->randomizer->getInt(1, 12) * self::STEP;

            if ($days[$from] - $delta < self::DAY_MIN || $days[$to] + $delta > self::DAY_MAX) {
                continue;
            }

            $days[$from] -= $delta;
            $days[$to] += $delta;
        }

        return $days;
    }

    private function blocksForDay(Carbon $day, int $work): array
    {
        $breaks = $this->breaksFor($work);
        $gross = $work + array_sum($breaks);

        $startFrom = max(self::EARLIEST_START, self::EARLIEST_END - $gross);
        $startTo = min(self::LATEST_START, self::LATEST_END - $gross);
        $start = $this->randomStep($startFrom, max($startFrom, $startTo));

        $chunks = $this->workChunks($work, count($breaks) + 1);
        $cursor = $day->copy()->startOfDay()->addSeconds($start);
        $blocks = [];

        foreach ($chunks as $index => $chunk) {
            $blocks[] = $this->block(EntryType::Work, $cursor, $chunk, $this->note());
            $cursor = $cursor->copy()->addSeconds($chunk);

            if (isset($breaks[$index])) {
                $blocks[] = $this->block(EntryType::Break, $cursor, $breaks[$index], null);
                $cursor = $cursor->copy()->addSeconds($breaks[$index]);
            }
        }

        return $blocks;
    }

    private function breaksFor(int $work): array
    {
        $min = max(self::BREAK_MIN, self::EARLIEST_END - self::LATEST_START - $work);
        $max = min(self::BREAK_MAX, self::LATEST_END - self::EARLIEST_START - $work);
        $total = $this->randomStep($min, max($min, $max));

        if ($total < self::BREAK_MIN + 900 || $this->randomizer->getInt(1, 10) > 4) {
            return [$total];
        }

        $short = $this->randomStep(900, min(1_500, $total - self::BREAK_MIN));

        return [$short, $total - $short];
    }

    private function workChunks(int $work, int $count): array
    {
        if ($count === 1) {
            return [$work];
        }

        $weights = $count === 2
            ? [$this->randomizer->getInt(40, 60)]
            : [$this->randomizer->getInt(28, 38), $this->randomizer->getInt(38, 50)];

        $chunks = [];
        $used = 0;

        foreach ($weights as $weight) {
            $chunk = intdiv((int) round($work * $weight / 100), self::STEP) * self::STEP;
            $chunks[] = $chunk;
            $used += $chunk;
        }

        $chunks[] = $work - $used;

        return $chunks;
    }

    private function block(EntryType $type, Carbon $start, int $seconds, ?string $note): array
    {
        return [
            'type' => $type->value,
            'started_at' => $start->toDateTimeString(),
            'ended_at' => $start->copy()->addSeconds($seconds)->toDateTimeString(),
            'note' => $note,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    private function note(): ?string
    {
        if ($this->randomizer->getInt(1, 10) > 6) {
            return null;
        }

        return self::NOTES[$this->randomizer->getInt(0, count(self::NOTES) - 1)];
    }

    private function randomStep(int $from, int $to): int
    {
        $steps = intdiv($to - $from, self::STEP);

        return $from + $this->randomizer->getInt(0, max(0, $steps)) * self::STEP;
    }
}
