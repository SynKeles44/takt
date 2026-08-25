@use('App\Support\Duration')

<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.month_summary.label') }}</h2>
        <span class="pill">{{ $month->isoFormat('MMMM') }}</span>
    </div>

    <p class="metric mt-4 text-3xl font-bold text-work-text">{{ Duration::human($totals['work']) }}</p>

    <dl class="mt-4 space-y-2 text-xs">
        <div class="flex items-center justify-between gap-3">
            <dt class="text-faint">{{ __('app.widget.month_summary.days') }}</dt>
            <dd class="metric text-ink">{{ $days }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-faint">{{ __('app.widget.month_summary.average') }}</dt>
            <dd class="metric text-ink">{{ $average > 0 ? Duration::human($average) : '–' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-faint">{{ __('app.widget.month_summary.balance') }}</dt>
            <dd @class(['metric', 'text-danger-text' => $totals['work'] < $target, 'text-work-text' => $totals['work'] >= $target])>
                {{ Duration::signed($totals['work'] - $target) }}
            </dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-faint">{{ __('app.chart.legend_break') }}</dt>
            <dd class="metric text-rest-text">{{ Duration::human($totals['break']) }}</dd>
        </div>
    </dl>
</x-card>
