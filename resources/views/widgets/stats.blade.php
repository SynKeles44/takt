@use('App\Enums\EntryType')
@use('App\Support\Duration')

@php
    $isWork = $running?->type === EntryType::Work;
    $runningSeconds = $running?->durationInSeconds() ?? 0;
    $remaining = $dailyTarget - $todayTotals['work'];
@endphp

<div class="stack-grid grid sm:grid-cols-2 lg:grid-cols-4">
    <x-stat :label="__('app.stats.work_today')"
            :seconds="$todayTotals['work']"
            :base="$todayTotals['work'] - ($isWork ? $runningSeconds : 0)"
            :since="$isWork ? $running->started_at->toIso8601String() : null"
            tone="work"
            :target-seconds="$dailyTarget"
            :hint="$remaining > 0
                ? __('app.stats.remaining', ['duration' => Duration::human($remaining)])
                : ($remaining === 0 ? __('app.stats.reached') : __('app.stats.overtime', ['duration' => Duration::human(abs($remaining))]))">
        <x-slot:icon><x-icon name="briefcase" class="size-4"/></x-slot:icon>
    </x-stat>

    <x-stat :label="__('app.stats.break_today')"
            :seconds="$todayTotals['break']"
            :base="$todayTotals['break'] - (($running && ! $isWork) ? $runningSeconds : 0)"
            :since="($running && ! $isWork) ? $running->started_at->toIso8601String() : null"
            tone="rest"
            :hint="$today->isoFormat('dddd, D. MMMM')">
        <x-slot:icon><x-icon name="coffee" class="size-4"/></x-slot:icon>
    </x-stat>

    <x-stat :label="__('app.stats.week')"
            :seconds="$weekTotals['work']"
            :base="$weekTotals['work'] - ($isWork ? $runningSeconds : 0)"
            :since="$isWork ? $running->started_at->toIso8601String() : null"
            tone="accent"
            :target-seconds="$weeklyTarget"
            :hint="__('app.stats.target', ['hours' => (int) round($weeklyTarget / 3600)])">
        <x-slot:icon><x-icon name="calendar" class="size-4"/></x-slot:icon>
    </x-stat>

    <x-stat :label="__('app.stats.balance')"
            :value="Duration::signed($balance['seconds'])"
            :tone="$balance['seconds'] < 0 ? 'danger' : ($balance['seconds'] > 0 ? 'work' : 'neutral')"
            :hint="$balance['exempt_worked'] > 0
                ? __('app.stats.balance_hint_exempt', [
                    'hours' => Duration::signedDecimal($balance['seconds']),
                    'days' => $balance['days'],
                    'exempt' => Duration::human($balance['exempt_worked']),
                ])
                : __('app.stats.balance_hint', [
                    'hours' => Duration::signedDecimal($balance['seconds']),
                    'days' => $balance['days'],
                ])">
        <x-slot:icon><x-icon name="scale" class="size-4"/></x-slot:icon>
    </x-stat>
</div>
