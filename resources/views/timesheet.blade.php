@use('App\Support\Duration')
@use('App\Enums\EntryType')

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.month.timesheet') }} {{ $month->format('m/Y') }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #fff; color: #111; font: 13px/1.45 -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; }
        h1 { margin: 0 0 2px; font-size: 19px; }
        .muted { color: #666; font-size: 12px; }
        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 16px; }
        .totals { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 18px; }
        .totals div { border: 1px solid #ddd; padding: 8px 10px; }
        .totals span { display: block; font-size: 11px; color: #666; }
        .totals strong { font-size: 15px; font-variant-numeric: tabular-nums; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e5e5; font-variant-numeric: tabular-nums; }
        th { font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #555; border-bottom: 1px solid #111; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 700; border-top: 2px solid #111; border-bottom: none; }
        .sign { margin-top: 34px; display: flex; gap: 48px; }
        .sign div { flex: 1; border-top: 1px solid #111; padding-top: 6px; font-size: 11px; color: #555; }
        .print { position: fixed; top: 16px; right: 16px; padding: 8px 14px; border: 1px solid #111; background: #111; color: #fff; border-radius: 6px; font-size: 12px; cursor: pointer; }
        @media screen { .head { padding-right: 96px; } }
        @media print { .print { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <button class="print" onclick="window.print()">{{ __('app.month.print') }}</button>

    <div class="head">
        <div>
            <h1>{{ __('app.month.timesheet') }} — {{ $month->isoFormat('MMMM YYYY') }}</h1>
            <p class="muted">{{ $user->name }} · {{ $user->email }} · {{ __('app.settings.weekly_hours') }}: {{ rtrim(rtrim(number_format($user->weekly_hours, 2, ',', ''), '0'), ',') }} h / {{ $user->working_days }} {{ __('app.settings.working_days') }}</p>
        </div>
        <div class="muted">{{ config('app.name') }}<br>{{ now()->isoFormat('D. MMMM YYYY') }}</div>
    </div>

    <div class="totals">
        <div><span>{{ __('app.month.work') }}</span><strong>{{ Duration::human($work) }}</strong></div>
        <div><span>{{ __('app.type.break') }}</span><strong>{{ Duration::human($break) }}</strong></div>
        <div><span>{{ __('app.month.target') }}</span><strong>{{ Duration::human($target) }}</strong></div>
        <div><span>{{ __('app.stats.balance') }}</span><strong>{{ Duration::signed($balance) }}</strong></div>
        <div><span>{{ __('app.month.days') }}</span><strong>{{ $bookedDays }} · Ø {{ Duration::human($average) }}</strong></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('app.form.date') }}</th>
                <th>{{ __('app.form.type') }}</th>
                <th>{{ __('app.form.start') }}</th>
                <th>{{ __('app.form.end') }}</th>
                <th class="num">{{ __('app.form.duration') }}</th>
                <th>{{ __('app.form.note') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($days as $date => $entries)
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ $loop->first ? \Illuminate\Support\Carbon::parse($date)->isoFormat('dd, DD.MM.') : '' }}</td>
                        <td>{{ $entry->type->label() }}</td>
                        <td>{{ $entry->started_at->format('H:i') }}</td>
                        <td>{{ $entry->ended_at?->format('H:i') ?? '—' }}</td>
                        <td class="num">{{ Duration::human($entry->durationInSeconds()) }}</td>
                        <td>{{ $entry->note }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="6">{{ __('app.month.empty') }}</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4">{{ __('app.month.work') }}</td>
                <td class="num">{{ Duration::human($work) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="sign">
        <div>{{ __('app.month.sign_employee') }}</div>
        <div>{{ __('app.month.sign_manager') }}</div>
    </div>
</body>
</html>
