<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StartTimerRequest;
use App\Services\TimeTracker;
use Illuminate\Http\RedirectResponse;

class TimerController extends Controller
{
    public function start(StartTimerRequest $request, TimeTracker $tracker): RedirectResponse
    {
        $at = $request->startedAt();
        $entry = $tracker->start($request->type(), $at);

        return back()->with('status', $at === null
            ? __('app.flash.started', ['type' => $entry->type->label()])
            : __('app.flash.started_at', ['type' => $entry->type->label(), 'time' => $at->format('H:i')]));
    }

    public function stop(TimeTracker $tracker): RedirectResponse
    {
        $entry = $tracker->stop();

        if ($entry === null) {
            return back()->with('status', __('app.flash.nothing_running'));
        }

        return back()->with('status', __('app.flash.stopped', ['type' => $entry->type->label()]));
    }
}
