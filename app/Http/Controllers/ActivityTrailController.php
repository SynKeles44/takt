<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ActivityTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ActivityTrailController extends Controller
{
    /** The app shell hands over what it observed; nothing is recorded while the trail is off. */
    public function store(Request $request, ActivityTrail $trail): JsonResponse
    {
        $data = $request->validate([
            'spans' => ['present', 'array', 'max:200'],
            'spans.*.app' => ['required', 'string', 'max:200'],
            'spans.*.title' => ['nullable', 'string', 'max:400'],
            'spans.*.starts_at' => ['required', 'date'],
            'spans.*.ends_at' => ['required', 'date'],
        ]);

        $user = $request->user();

        return response()->json([
            'recorded' => $trail->record($user, $data['spans']),
            'enabled' => $trail->enabled($user),
        ]);
    }

    public function update(Request $request, ActivityTrail $trail): RedirectResponse
    {
        $data = $request->validate([
            'activity_trail' => ['required', 'boolean'],
            'activity_retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user = $request->user();

        $user->update([
            'activity_trail' => (bool) $data['activity_trail'],
            'activity_retention_days' => (int) ($data['activity_retention_days'] ?? $user->activity_retention_days),
        ]);

        if (! $data['activity_trail']) {
            // switching it off is not "stop writing" but "this was not wanted"
            $user->activitySpans()->delete();
        }

        $trail->prune($user);

        return back()->with('status', __('app.trail.'.($data['activity_trail'] ? 'on_done' : 'off_done')));
    }
}
