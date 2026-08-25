<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Widget;
use App\Models\DashboardWidget;
use App\Services\Dashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * The dashboard is arranged on the dashboard itself: the edit mode sends the whole
 * layout — order, width, height — in one go, so a drag never leaves a half state.
 */
class DashboardLayoutController extends Controller
{
    public function arrange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'widgets' => ['present', 'array', 'max:60'],
            'widgets.*.widget' => ['required', Rule::enum(Widget::class), 'distinct'],
            'widgets.*.span' => ['required', 'integer', 'min:2', 'max:6'],
            'widgets.*.rows' => ['required', 'integer', 'min:1', 'max:8'],
        ]);

        DashboardWidget::query()->delete();
        $request->user()->forceFill(['dashboard_arranged' => true])->save();

        foreach ($data['widgets'] as $position => $entry) {
            DashboardWidget::query()->create([
                'widget' => Widget::from($entry['widget']),
                'span' => (int) $entry['span'],
                'rows' => (int) $entry['rows'],
                'position' => $position,
            ]);
        }

        return response()->json(['saved' => true]);
    }

    /**
     * One widget, rendered on its own. The edit mode pulls this when a widget is pushed onto
     * the board, so a fresh tile shows its real content long before anything is stored.
     */
    public function preview(Request $request, Dashboard $dashboard, Widget $widget): Response
    {
        $user = $request->user();

        return response()->view($widget->view(), $dashboard->data($widget, $user));
    }

    public function reset(Request $request, Dashboard $dashboard): RedirectResponse
    {
        $dashboard->reset($request->user());

        return back()->with('status', __('app.widget.reset_done'));
    }
}
