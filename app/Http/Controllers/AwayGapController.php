<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AwayGap;
use App\Services\AwayTime;
use App\Services\TimeTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AwayGapController extends Controller
{
    /** The app shell reports a lock or sleep once the Mac is back. */
    public function store(Request $request, AwayTime $away): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
        ]);

        $gap = $away->record(Carbon::parse($data['from']), Carbon::parse($data['to']));

        return response()->json(['recorded' => $gap !== null]);
    }

    public function update(Request $request, AwayGap $gap, AwayTime $away, TimeTracker $tracker): RedirectResponse
    {
        $data = $request->validate(['answer' => ['required', 'in:break,shorten,keep']]);

        match ($data['answer']) {
            'break' => $away->asBreak($gap, $tracker),
            'shorten' => $away->shorten($gap, $tracker),
            default => $away->keep($gap),
        };

        return back()->with('status', __('app.away.'.$data['answer'].'_done'));
    }
}
