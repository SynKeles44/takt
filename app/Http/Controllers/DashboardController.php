<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Widget;
use App\Enums\WidgetGroup;
use App\Services\Dashboard;
use App\Services\TodoMaintenance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Dashboard $dashboard, TodoMaintenance $maintenance): View
    {
        $request->validate([
            'woche' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $maintenance->run();

        $user = $request->user();
        $week = $request->filled('woche') ? $request->string('woche')->toString() : null;

        $layout = $dashboard->layout($user);

        $widgets = $layout->map(fn ($widget): array => [
            'widget' => $widget->widget,
            'columns' => $widget->columns(),
            'rows' => $widget->rowSpan(),
            'data' => $dashboard->data($widget->widget, $user, $week),
        ]);

        $used = $layout->map(fn ($widget): string => $widget->widget->value)->all();

        return view('dashboard', [
            'widgets' => $widgets,
            // the drawer offers what is not on the board yet, grouped like the catalogue
            'available' => collect(WidgetGroup::cases())
                ->map(fn (WidgetGroup $group): array => [
                    'group' => $group,
                    'widgets' => collect(Widget::cases())
                        ->filter(fn (Widget $widget): bool => $widget->group() === $group)
                        ->reject(fn (Widget $widget): bool => in_array($widget->value, $used, true))
                        ->values(),
                ])
                ->filter(fn (array $entry): bool => $entry['widgets']->isNotEmpty())
                ->values(),
            'groups' => WidgetGroup::cases(),
            'catalog' => Widget::cases(),
        ]);
    }
}
