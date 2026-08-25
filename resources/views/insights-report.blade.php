@use('App\Support\Duration')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.insights.report') }} {{ $title }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #fff; color: #111; font: 13px/1.45 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; }
        h1 { margin: 0 0 2px; font-size: 19px; }
        h2 { margin: 22px 0 8px; font-size: 13px; text-transform: uppercase; letter-spacing: .08em; color: #555; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 10px; }
        .totals { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 16px; }
        .totals div { border: 1px solid #ddd; padding: 8px 10px; }
        .totals span { display: block; font-size: 11px; color: #666; }
        .totals strong { font-size: 15px; font-variant-numeric: tabular-nums; }
        .totals em { display: block; font-size: 10px; color: #777; font-style: normal; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 5px 8px; border-bottom: 1px solid #e5e5e5; font-variant-numeric: tabular-nums; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #555; border-bottom: 1px solid #111; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 700; border-top: 2px solid #111; border-bottom: none; }
        .bar { display: block; height: 6px; background: #e8e8e8; position: relative; }
        .bar i { display: block; height: 100%; background: #2f7d55; }
        .bar u { position: absolute; top: -2px; bottom: -2px; width: 1px; background: #111; text-decoration: none; }
        .tasks { columns: 2; column-gap: 26px; margin: 0; padding-left: 18px; }
        .tasks li { break-inside: avoid; font-size: 12px; margin-bottom: 3px; }
        .foot { margin-top: 26px; border-top: 1px solid #ddd; padding-top: 8px; font-size: 10px; color: #777; }
        .print { position: fixed; top: 16px; right: 16px; padding: 8px 14px; border: 1px solid #111; background: #111; color: #fff; border-radius: 6px; font-size: 12px; cursor: pointer; }
        @media screen { .head { padding-right: 96px; } }
        @media print { .print { display: none; } body { padding: 0; } h2 { break-after: avoid; } table { break-inside: auto; } tr { break-inside: avoid; } }
    </style>
</head>
<body>
    <button class="print" onclick="window.print()">{{ __('app.month.print') }}</button>

    <div class="head">
        <div>
            <h1>{{ __('app.insights.report') }} — {{ $title }}</h1>
            <p class="muted">
                {{ $user->name }} · {{ $user->email }} ·
                {{ __('app.settings.weekly_hours') }}: {{ rtrim(rtrim(number_format($user->weekly_hours, 2, ',', ''), '0'), ',') }} h /
                {{ $user->working_days }} {{ __('app.settings.working_days') }}
            </p>
        </div>
        <p class="muted">{{ now()->isoFormat('D. MMMM YYYY, HH:mm') }}</p>
    </div>

    <div class="totals">
        <div>
            <span>{{ __('app.chart.legend_work') }}</span>
            <strong>{{ Duration::human($work) }}</strong>
            <em>{{ __('app.insights.break_foot', ['value' => Duration::human($break)]) }}</em>
        </div>
        <div>
            <span>{{ __('app.month.target') }}</span>
            <strong>{{ Duration::human($target) }}</strong>
            <em>{{ __('app.insights.days_foot', ['count' => $bookedDays]) }}</em>
        </div>
        <div>
            <span>{{ __('app.stats.balance') }}</span>
            <strong>{{ Duration::signed($balance) }}</strong>
            <em>{{ Duration::signedDecimal($balance) }} h</em>
        </div>
        <div>
            <span>{{ __('app.insights.done') }}</span>
            <strong>{{ $completedCount }}</strong>
            <em>{{ __('app.insights.longest_foot', ['value' => $longest > 0 ? Duration::human($longest) : '–']) }}</em>
        </div>
    </div>

    <h2>{{ __('app.insights.distribution') }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('app.form.date') }}</th>
                <th>{{ __('app.chart.legend_work') }}</th>
                <th class="num">{{ __('app.month.target') }}</th>
                <th class="num">{{ __('app.stats.balance') }}</th>
                <th>{{ __('app.absence.title') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($buckets as $bucket)
                <tr>
                    <td>{{ $bucket['label'] }} {{ $bucket['sub'] }}</td>
                    <td>
                        <span class="bar" style="width: 130px">
                            <i style="width: {{ $bucket['work'] > 0 ? max(2, round($bucket['work'] / $peak * 100)) : 0 }}%"></i>
                            @if ($bucket['target'] > 0)
                                <u style="left: {{ min(100, round($bucket['target'] / $peak * 100)) }}%"></u>
                            @endif
                        </span>
                    </td>
                    <td class="num">{{ $bucket['work'] > 0 ? Duration::human($bucket['work']) : '–' }}</td>
                    <td class="num">{{ $bucket['target'] > 0 || $bucket['work'] > 0 ? Duration::signed($bucket['work'] - $bucket['target']) : '' }}</td>
                    <td>{{ $bucket['note'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>{{ __('app.month.work') }}</td>
                <td></td>
                <td class="num">{{ Duration::human($work) }}</td>
                <td class="num">{{ Duration::signed($balance) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <h2>{{ __('app.insights.completed') }} ({{ $completedCount }})</h2>

    @if ($completed->isEmpty())
        <p class="muted">{{ __('app.insights.empty_tasks') }}</p>
    @else
        <ul class="tasks">
            @foreach ($completed as $todo)
                <li>{{ $todo->completed_at?->isoFormat('D. MMM') }} — {{ $todo->title }}</li>
            @endforeach
        </ul>
    @endif

    <p class="foot">{{ __('app.insights.report_foot', ['app' => config('app.name')]) }}</p>
</body>
</html>
