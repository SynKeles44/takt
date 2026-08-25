<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EntryType;
use App\Http\Requests\StoreTimeEntriesRequest;
use App\Http\Requests\TimeEntryRequest;
use App\Models\TimeEntry;
use App\Services\EntryAdjuster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TimeEntryController extends Controller
{
    public function store(StoreTimeEntriesRequest $request): RedirectResponse
    {
        $entries = $request->plan();

        DB::transaction(function () use ($entries): void {
            foreach ($entries as $attributes) {
                TimeEntry::query()->create($attributes);
            }
        });

        return back()->with('status', trans_choice('app.flash.created', count($entries)));
    }

    public function edit(TimeEntry $entry): View
    {
        return view('entries.edit', [
            'entry' => $entry,
            'types' => EntryType::cases(),
        ]);
    }

    public function update(TimeEntryRequest $request, TimeEntry $entry, EntryAdjuster $adjuster): RedirectResponse
    {
        $adjusted = DB::transaction(function () use ($request, $entry, $adjuster): int {
            $neighbours = $adjuster->adjustments($request->period(), $entry->getKey());

            $neighbours->each(fn (TimeEntry $neighbour): bool => $neighbour->save());
            $entry->update($request->payload());

            return $neighbours->count();
        });

        return redirect()
            ->route('history', ['from' => $entry->started_at->copy()->startOfWeek()->toDateString()])
            ->with('status', $adjusted === 0
                ? __('app.flash.updated')
                : trans_choice('app.flash.updated_adjusted', $adjusted));
    }

    public function destroy(TimeEntry $entry): RedirectResponse
    {
        $week = $entry->started_at->copy()->startOfWeek()->toDateString();

        $entry->delete();

        // coming from the entry's own edit page there is nothing left to go back to
        $onOwnPage = parse_url((string) url()->previous(), PHP_URL_PATH)
            === parse_url(route('entries.edit', $entry), PHP_URL_PATH);

        $redirect = $onOwnPage ? redirect()->route('history', ['from' => $week]) : back();

        return $redirect
            ->with('status', __('app.flash.deleted'))
            ->with('undo', ['url' => route('trash.entry.restore', $entry), 'label' => __('app.trash.undo')]);
    }

    public function destroyDay(string $date): RedirectResponse
    {
        abort_unless(Carbon::hasFormat($date, 'Y-m-d'), 404);

        $day = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        $deleted = TimeEntry::query()->onDay($day)->delete();

        return back()->with('status', trans_choice('app.flash.day_deleted', $deleted, [
            'date' => $day->isoFormat('D. MMMM YYYY'),
        ]));
    }
}
