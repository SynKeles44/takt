<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\CalendarEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarEventController extends Controller
{
    /** The app shell hands over what the Mac's calendars hold for a day. */
    public function __invoke(Request $request, CalendarEvents $calendar): JsonResponse
    {
        $data = $request->validate([
            'day' => ['required', 'date_format:Y-m-d'],
            'events' => ['present', 'array', 'max:100'],
            'events.*.title' => ['required', 'string', 'max:300'],
            'events.*.starts_at' => ['required', 'date'],
            'events.*.ends_at' => ['required', 'date'],
            'events.*.calendar' => ['nullable', 'string', 'max:120'],
        ]);

        $stored = $calendar->store($request->user(), Carbon::parse($data['day']), $data['events']);

        return response()->json(['stored' => count($stored)]);
    }
}
