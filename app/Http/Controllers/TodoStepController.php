<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Todo;
use App\Models\TodoStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TodoStepController extends Controller
{
    public function store(Request $request, Todo $todo): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $todo->steps()->create([
            'title' => trim($data['title']),
            'position' => (int) $todo->steps()->max('position') + 1,
        ]);

        return back()->with('status', __('app.flash.step_created'));
    }

    public function toggle(Request $request, Todo $todo, TodoStep $step): RedirectResponse|JsonResponse
    {
        $step->toggle();

        $status = $step->isDone() ? __('app.flash.step_done') : __('app.flash.step_reopened');

        if ($request->expectsJson()) {
            return response()->json(['done' => $step->isDone(), 'reload' => false, 'status' => $status]);
        }

        return back()->with('status', $status);
    }

    public function destroy(Todo $todo, TodoStep $step): RedirectResponse
    {
        $step->delete();

        return back()->with('status', __('app.flash.step_deleted'));
    }
}
