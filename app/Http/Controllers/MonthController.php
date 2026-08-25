<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EntryType;
use App\Models\TimeEntry;
use App\Services\TimeTracker;
use App\Services\WorkCalendar;
use App\Support\Duration;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthController extends Controller
{
    public function timesheet(Request $request, TimeTracker $tracker, WorkCalendar $calendar): View
    {
        $month = $this->month($request);

        return view('timesheet', $this->summary($month, $tracker, $request->user()->dailyTargetSeconds(), $this->exempt($request, $calendar, $month)) + [
            'user' => $request->user(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $month = $this->month($request);
        $entries = $this->entries($month);

        $filename = sprintf('takt-%s.csv', $month->format('Y-m'));

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Datum', 'Art', 'Von', 'Bis', 'Dauer', 'Dezimal', 'Notiz'], ';');

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->started_at->format('d.m.Y'),
                    $entry->type->label(),
                    $entry->started_at->format('H:i'),
                    $entry->ended_at?->format('H:i') ?? '',
                    Duration::human($entry->durationInSeconds()),
                    str_replace('.', ',', (string) Duration::decimalHours($entry->durationInSeconds())),
                    $entry->note ?? '',
                ], ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    private function month(Request $request): Carbon
    {
        $request->validate(['monat' => ['nullable', 'date_format:Y-m']]);

        return ($request->filled('monat')
            ? Carbon::createFromFormat('Y-m-d', $request->string('monat')->toString().'-01')
            : Carbon::today())->startOfMonth();
    }

    private function entries(Carbon $month): Collection
    {
        return TimeEntry::query()
            ->between($month, $month->copy()->endOfMonth()->endOfDay())
            ->orderBy('started_at')
            ->get();
    }

    private function exempt(Request $request, WorkCalendar $calendar, Carbon $month): array
    {
        return $calendar->exemptions($request->user(), $month, $month->copy()->endOfMonth());
    }

    /** @param array<string, array{label: string, tone: string, absence: mixed}> $exemptions */
    private function summary(Carbon $month, TimeTracker $tracker, int $dailyTarget, array $exemptions = []): array
    {
        $entries = $this->entries($month);
        $byDay = $entries->groupBy(fn (TimeEntry $entry): string => $entry->started_at->toDateString());

        $work = (int) $entries->where('type', EntryType::Work)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());
        $break = (int) $entries->where('type', EntryType::Break)->sum(fn (TimeEntry $entry): int => $entry->durationInSeconds());

        $workDays = $byDay->filter(
            fn (Collection $day): bool => $day->where('type', EntryType::Work)->isNotEmpty(),
        );

        $bookedDays = $workDays->count();
        $targetDays = $workDays->keys()->reject(fn (string $date): bool => isset($exemptions[$date]))->count();

        return [
            'month' => $month,
            'days' => $byDay,
            'work' => $work,
            'break' => $break,
            'bookedDays' => $bookedDays,
            'target' => $targetDays * $dailyTarget,
            'balance' => $work - $targetDays * $dailyTarget,
            'exemptions' => $exemptions,
            'average' => $bookedDays > 0 ? (int) round($work / $bookedDays) : 0,
            'dailyTarget' => $dailyTarget,
        ];
    }
}
