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

    /** The day windows the statistic offers besides a range of one's own. */
    public const array HOME_OFFICE_WINDOWS = [7, 30, 365];

    /**
     * The period the home-office statistic reads. A range the user picked wins over the day
     * window — that is the whole rule, and it is here rather than in a view so both places that
     * show the statistic answer the same way.
     *
     * @return array{from: Carbon, to: Carbon, window: ?int, custom: bool}
     */
    public function homeOfficePeriod(User $user, ?CarbonInterface $until = null): array
    {
        $today = Carbon::instance(($until ?? Carbon::today())->toDateTimeImmutable())->startOfDay();

        if ($user->home_office_from !== null && $user->home_office_to !== null) {
            $from = Carbon::parse($user->home_office_from)->startOfDay();
            $to = Carbon::parse($user->home_office_to)->endOfDay();

            if ($from->lessThanOrEqualTo($to)) {
                return ['from' => $from, 'to' => $to, 'window' => null, 'custom' => true];
            }
        }

        $window = in_array((int) $user->home_office_window, self::HOME_OFFICE_WINDOWS, true)
            ? (int) $user->home_office_window
            : 30;

        return [
            'from' => $today->copy()->subDays($window - 1),
            'to' => $today->copy()->endOfDay(),
            'window' => $window,
            'custom' => false,
        ];
    }

    /**
     * How the home-office rhythm compares to the one that was agreed: days in the period, days
     * in the calendar year, and the average per week derived from the period's length.
     *
     * @return array{year: int, days_year: int, days_window: int, window: ?int, custom: bool,
     *     from: Carbon, to: Carbon, days: int, per_week: float, target: int, weeks: float}
     */
    public function homeOfficeSummary(User $user, ?CarbonInterface $until = null): array
    {
        $period = $this->homeOfficePeriod($user, $until);
        $to = $period['to'];

        $inYear = count($this->homeOfficeDates($to->copy()->startOfYear(), $to));
        $inPeriod = count($this->homeOfficeDates($period['from'], $to));

        // the average is per week of the period, so a range of any length stays comparable
        $days = max(1, (int) $period['from']->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1);
        $weeks = $days / 7;

        return [
            'year' => $to->year,
            'days_year' => $inYear,
            'days_window' => $inPeriod,
            'window' => $period['window'],
            'custom' => $period['custom'],
            'from' => $period['from'],
            'to' => $to,
            'days' => $days,
            'weeks' => round($weeks, 1),
            'per_week' => round($inPeriod / $weeks, 1),
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
