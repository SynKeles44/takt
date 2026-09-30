<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Services\BulkEntryPlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Filling several days in one go — the counterpart to booking a single day by hand.
 */
class BulkEntryController extends Controller
{
    public function store(Request $request, BulkEntryPlanner $planner): RedirectResponse
    {
        $data = $request->validate([
            'tage' => ['required', 'array', 'min:1', 'max:366'],
            'tage.*' => ['date_format:Y-m-d'],
            'von' => ['required', 'date_format:H:i'],
            'bis' => ['required', 'date_format:H:i', 'after:von'],
            'pause_von' => ['nullable', 'required_with:pause_bis', 'date_format:H:i'],
            'pause_bis' => ['nullable', 'required_with:pause_von', 'date_format:H:i', 'after:pause_von'],
            // a scatter beyond two hours stops being a variation and becomes a different day
            'streuung' => ['nullable', 'integer', 'min:0', 'max:120'],
            'ueberschreiben' => ['nullable', 'boolean'],
            'feiertage' => ['nullable', 'boolean'],
        ]);

        $result = $planner->plan(
            days: array_values(array_unique($data['tage'])),
            workFrom: $data['von'],
            workTo: $data['bis'],
            breakFrom: $data['pause_von'] ?? null,
            breakTo: $data['pause_bis'] ?? null,
            scatterMinutes: (int) ($data['streuung'] ?? 0),
            skipBooked: ! $request->boolean('ueberschreiben'),
            skipExempt: ! $request->boolean('feiertage'),
        );

        if ($result['entries'] === []) {
            return back()->with('status', __('app.bulk.nothing'));
        }

        DB::transaction(function () use ($result): void {
            foreach ($result['entries'] as $attributes) {
                TimeEntry::query()->create($attributes);
            }
        });

        $days = collect($result['entries'])
            ->map(fn (array $entry): string => $entry['started_at']->toDateString())
            ->unique()
            ->count();

        $skipped = count($result['skipped'][BulkEntryPlanner::SKIP_BOOKED])
            + count($result['skipped'][BulkEntryPlanner::SKIP_EXEMPT]);

        return back()->with('status', $skipped === 0
            ? __('app.bulk.done', ['days' => $days, 'entries' => count($result['entries'])])
            : __('app.bulk.done_skipped', ['days' => $days, 'entries' => count($result['entries']), 'skipped' => $skipped]));
    }
}
