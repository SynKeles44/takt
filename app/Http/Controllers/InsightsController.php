<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Insights;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class InsightsController extends Controller
{
    public function __invoke(Request $request, Insights $insights): View
    {
        return view('insights', $this->data($request, $insights));
    }

    public function report(Request $request, Insights $insights): View
    {
        return view('insights-report', $this->data($request, $insights) + ['user' => $request->user()]);
    }

    private function data(Request $request, Insights $insights): array
    {
        $request->validate([
            'zeitraum' => ['nullable', 'in:'.implode(',', Insights::PERIODS)],
            'stand' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $anchor = $request->filled('stand')
            ? Carbon::createFromFormat('Y-m-d', $request->string('stand')->toString())
            : Carbon::today();

        return $insights->build(
            $request->user(),
            $insights->period($request->string('zeitraum')->toString()),
            $anchor,
        );
    }
}
