<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AbsenceType;
use App\Http\Requests\AbsenceRequest;
use App\Models\Absence;
use App\Services\Holidays;
use App\Services\WorkCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AbsenceController extends Controller
{
    public function index(Request $request, WorkCalendar $calendar, Holidays $holidays): View
    {
        $year = (int) ($request->integer('jahr') ?: Carbon::today()->year);
        $user = $request->user();

        return view('absences', [
            'absences' => Absence::query()->orderByDesc('starts_on')->get(),
            'types' => AbsenceType::cases(),
            'vacation' => $calendar->vacationSummary($user, $year),
            'homeOffice' => $calendar->homeOfficeSummary($user, until: $year === Carbon::today()->year ? null : Carbon::create($year, 12, 31)),
            'holidays' => $holidays->forYear($year, $user->holiday_region),
            'year' => $year,
            'region' => Holidays::regions()[$user->holiday_region] ?? $user->holiday_region,
        ]);
    }

    public function store(AbsenceRequest $request): RedirectResponse
    {
        Absence::query()->create($request->payload());

        return back()->with('status', __('app.absence.saved'));
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        $absence->delete();

        return back()->with('status', __('app.absence.deleted'));
    }
}
