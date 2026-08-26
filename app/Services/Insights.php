<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Models\Todo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class Insights
{
    public const PERIODS = ['woche', 'monat', 'jahr'];

    public function __construct(private readonly WorkCalendar $calendar) {}

    public function build(User $user, string $period, ?Carbon $anchor = null): array
    {
        $anchor ??= Carbon::today();

        [$from, $to] = $this->range($period, $anchor);

        $entries = TimeEntry::query()->between($from, $to->copy()->endOfDay())->orderBy('started_at')->get();
        $exemptions = $this->calendar->exemptions($user, $from, $to);
        $dailyTarget = $user->dailyTargetSeconds();

        $perDay = $entries
            ->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString())
            ->map(fn (Collection $day): int => (int) $day->where('type', EntryType::Work)
                ->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds()));

        $work = (int) $perDay->sum();
        $break = (int) $entries->where('type', EntryType::Break)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

        $bookedDays = $perDay->filter(fn (int $seconds): bool => $seconds > 0);
        $targetDays = $bookedDays->keys()
            ->reject(fn (string $date): bool => $exemptions[$date]['blocking'] ?? false)
            ->count();

        $buckets = $this->buckets($period, $from, $to, $perDay, $exemptions, $dailyTarget);

        return [
            'period' => $period,
            'from' => $from,
            'to' => $to,
            'title' => $this->title($period, $from, $to),
            'previous' => $this->shift($period, $from, -1),
            'next' => $this->shift($period, $from, 1),
            'isCurrent' => $this->isCurrent($period, $from),
            'month' => $from->format('Y-m'),
            'work' => $work,
            'break' => $break,
            'target' => $targetDays * $dailyTarget,
            'balance' => $work - $targetDays * $dailyTarget,
            'bookedDays' => $bookedDays->count(),
            'average' => $bookedDays->isNotEmpty() ? (int) round($work / $bookedDays->count()) : 0,
            'longest' => (int) ($perDay->max() ?? 0),
            'buckets' => $buckets,
            'peak' => max(1, (int) collect($buckets)->max('work')),
            'dailyTarget' => $dailyTarget,
            'heatmap' => $period === 'jahr' ? $this->heatmap($from, $perDay) : null,
            'completed' => Todo::query()
                ->done()
                ->whereBetween('completed_at', [$from, $to->copy()->endOfDay()])
                ->orderByDesc('completed_at')
                ->limit(12)
                ->get(),
            'completedCount' => Todo::query()
                ->done()
                ->whereBetween('completed_at', [$from, $to->copy()->endOfDay()])
                ->count(),
        ];
    }

    public function period(?string $value): string
    {
        return in_array($value, self::PERIODS, true) ? $value : 'woche';
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(string $period, Carbon $anchor): array
    {
        return match ($period) {
            'monat' => [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()],
            'jahr' => [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()],
            default => [$anchor->copy()->startOfWeek(), $anchor->copy()->endOfWeek()],
        };
    }

    private function title(string $period, Carbon $from, Carbon $to): string
    {
        return match ($period) {
            'monat' => $from->isoFormat('MMMM YYYY'),
            'jahr' => $from->format('Y'),
            default => $from->isoFormat('D. MMM').' – '.$to->isoFormat('D. MMM YYYY'),
        };
    }

    private function shift(string $period, Carbon $from, int $steps): string
    {
        return match ($period) {
            'monat' => $from->copy()->addMonths($steps)->toDateString(),
            'jahr' => $from->copy()->addYears($steps)->toDateString(),
            default => $from->copy()->addWeeks($steps)->toDateString(),
        };
    }

    private function isCurrent(string $period, Carbon $from): bool
    {
        $today = Carbon::today();

        return match ($period) {
            'monat' => $from->isSameMonth($today),
            'jahr' => $from->year === $today->year,
            default => $from->isSameWeek($today),
        };
    }

    /**
     * One uniform bar per bucket — days for week and month, months for the year.
     *
     * @return list<array{label: string, sub: string, work: int, target: int, note: ?string, tone: ?string, today: bool}>
     */
    private function buckets(string $period, Carbon $from, Carbon $to, Collection $perDay, array $exemptions, int $dailyTarget): array
    {
        $today = Carbon::today();

        if ($period === 'jahr') {
            $buckets = [];

            for ($month = $from->copy(); $month->lessThanOrEqualTo($to); $month->addMonth()) {
                $end = $month->copy()->endOfMonth();

                $work = (int) $perDay
                    ->filter(fn (int $seconds, string $date): bool => str_starts_with($date, $month->format('Y-m')))
                    ->sum();

                $days = $perDay
                    ->filter(fn (int $seconds, string $date): bool => $seconds > 0 && str_starts_with($date, $month->format('Y-m')))
                    ->keys()
                    ->reject(fn (string $date): bool => $exemptions[$date]['blocking'] ?? false)
                    ->count();

                $buckets[] = [
                    'label' => $month->isoFormat('MMM'),
                    'sub' => $month->format('Y'),
                    'work' => $work,
                    'target' => $days * $dailyTarget,
                    'note' => null,
                    'tone' => null,
                    'today' => $month->isSameMonth($today),
                ];
            }

            return $buckets;
        }

        $buckets = [];

        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            $key = $day->toDateString();
            $exemption = $exemptions[$key] ?? null;

            $buckets[] = [
                'label' => $day->isoFormat('dd'),
                'sub' => $day->isoFormat('D.MM.'),
                'work' => (int) $perDay->get($key, 0),
                'target' => ($exemption['blocking'] ?? false) || $day->isWeekend() ? 0 : $dailyTarget,
                'note' => $exemption['label'] ?? null,
                'tone' => $exemption['tone'] ?? null,
                'today' => $day->isSameDay($today),
            ];
        }

        return $buckets;
    }

    /** @return list<list<array{date: Carbon, inRange: bool, work: int}>> */
    private function heatmap(Carbon $from, Collection $perDay): array
    {
        $start = $from->copy()->startOfWeek();
        $end = $from->copy()->endOfYear()->endOfWeek();

        $weeks = [];
        $column = [];

        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            $column[] = [
                'date' => $day->copy(),
                'inRange' => $day->year === $from->year,
                'work' => (int) $perDay->get($day->toDateString(), 0),
            ];

            if (count($column) === 7) {
                $weeks[] = $column;
                $column = [];
            }
        }

        return $weeks;
    }
}
