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
            $exempt[$date] = ['label' => $name, 'tone' => 'work', 'absence' => null];
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

                $exempt[$day->toDateString()] = [
                    'label' => $absence->note ?: $absence->type->label(),
                    'tone' => $absence->type->tone(),
                    'absence' => $absence,
                ];
            }
        }

        ksort($exempt);

        return $exempt;
    }

    /** @return list<string> */
    public function exemptDates(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        return array_keys($this->exemptions($user, $from, $to));
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
