<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AbsenceType;
use App\Models\Absence;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class WorkCalendar
{
    public function __construct(private readonly Holidays $holidays) {}

    /** @return array<string, array{label: string, tone: string, absence: ?Absence}> */
    public function exemptions(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $exempt = [];

        foreach ($this->holidays->between(Carbon::instance($from->toDateTimeImmutable()), Carbon::instance($to->toDateTimeImmutable()), $user->holiday_region) as $date => $name) {
            $exempt[$date] = ['label' => $name, 'tone' => 'work', 'absence' => null, 'blocking' => true];
        }

        $absences = Absence::query()
            ->overlapping($from, $to)
            ->orderBy('starts_on')
            ->get();

        foreach ($absences as $absence) {
            for ($day = $absence->starts_on->copy(); $day->lessThanOrEqualTo($absence->ends_on); $day->addDay()) {
                if ($day->lessThan($from) || $day->greaterThan($to)) {
                    continue;
                }

                $key = $day->toDateString();
                $blocking = $absence->type->blocksWork();

                if (! $blocking && ($exempt[$key]['blocking'] ?? false)) {
                    continue;
                }

                $exempt[$key] = [
                    'label' => $absence->note ?: $absence->type->label(),
                    'tone' => $absence->type->tone(),
                    'absence' => $absence,
                    'blocking' => $blocking,
                ];
            }
        }

        ksort($exempt);

        return $exempt;
    }

    /** @return list<string> the days that carry no target */
    public function exemptDates(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        return array_keys(array_filter(
            $this->exemptions($user, $from, $to),
            static fn (array $entry): bool => $entry['blocking'],
        ));
    }

    /**
     * Home office is a marker, not an absence: it shows up on the day and stays out of
     * every calculation. @return list<string>
     */
    public function homeOfficeDates(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];

        foreach (Absence::query()->where('type', AbsenceType::HomeOffice)->overlapping($from, $to)->get() as $absence) {
            for ($day = $absence->starts_on->copy(); $day->lessThanOrEqualTo($absence->ends_on); $day->addDay()) {
                if ($day->lessThan($from) || $day->greaterThan($to) || $day->isWeekend()) {
                    continue;
                }

                $dates[$day->toDateString()] = true;
            }
        }

        ksort($dates);

        return array_keys($dates);
    }

    /**
     * How the home-office rhythm compares to the one that was agreed: days this year, days
     * in the chosen window, and the average per week derived from that window.
     *
     * @return array{year: int, days_year: int, days_window: int, window: int, per_week: float, target: int, weeks: float}
     */
    public function homeOfficeSummary(User $user, ?int $window = null, ?CarbonInterface $until = null): array
    {
        $until = Carbon::instance(($until ?? Carbon::today())->toDateTimeImmutable())->endOfDay();
        $window = max(1, $window ?? $user->home_office_window);

        $inYear = count($this->homeOfficeDates($until->copy()->startOfYear(), $until));
        $inWindow = count($this->homeOfficeDates($until->copy()->subDays($window - 1)->startOfDay(), $until));
        $weeks = $window / 7;

        return [
            'year' => $until->year,
            'days_year' => $inYear,
            'days_window' => $inWindow,
            'window' => $window,
            'weeks' => round($weeks, 1),
            'per_week' => round($inWindow / $weeks, 1),
            'target' => (int) $user->home_office_days,
        ];
    }

    /** @return list<string> */
    public function exemptDatesForBalance(User $user, ?CarbonInterface $until = null): array
    {
        $until ??= Carbon::today();
        $first = TimeEntry::query()->min('started_at');

        $from = $first !== null ? Carbon::parse($first)->startOfYear() : Carbon::instance($until->toDateTimeImmutable())->startOfYear();

        return $this->exemptDates($user, $from, $until);
    }

    public function vacationSummary(User $user, ?int $year = null): array
    {
        $year ??= Carbon::today()->year;

        $taken = Absence::query()
            ->where('type', AbsenceType::Vacation)
            ->whereYear('starts_on', $year)
            ->get()
            ->sum(fn (Absence $absence): int => $absence->workdays());

        return [
            'entitlement' => (float) $user->vacation_days,
            'taken' => $taken,
            'remaining' => round((float) $user->vacation_days - $taken, 1),
            'year' => $year,
        ];
    }
}
