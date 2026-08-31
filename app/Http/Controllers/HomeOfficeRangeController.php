<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WorkCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Which period the home-office statistic reads. One endpoint for both places that show it — the
 * widget and the absence page — so the choice is the same wherever it is made.
 */
class HomeOfficeRangeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'window' => ['required_without_all:from,to', 'nullable', 'integer', Rule::in(WorkCalendar::HOME_OFFICE_WINDOWS)],
            'from' => ['required_with:to', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $user = $request->user();
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        // a range wins over a window, so choosing a window clears the range
        $user->forceFill($from !== null && $to !== null
            ? ['home_office_from' => $from, 'home_office_to' => $to]
            : ['home_office_window' => (int) $data['window'], 'home_office_from' => null, 'home_office_to' => null])
            ->save();

        return back();
    }
}
