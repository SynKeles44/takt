@use('App\Support\Duration')

<x-card>
    <h2 class="heading">{{ __('app.widget.week_balance.label') }}</h2>

    @php $peak = max($target, (int) $weeks->max('work'), 1); @endphp

    <div class="mt-4 flex h-24 items-end gap-1.5">
        @foreach ($weeks as $entry)
            @php $height = $entry['work'] > 0 ? max(4, round($entry['work'] / $peak * 100, 2)) : 0; @endphp
            <div class="flex h-full flex-1 flex-col justify-end gap-1" title="KW {{ $entry['start']->isoWeek() }}: {{ Duration::human($entry['work']) }}">
                <div @class([
                        'bar w-full',
                        'bg-gradient-to-t from-accent to-accent-2' => $entry['current'],
                        'bg-gradient-to-t from-work to-work-2' => ! $entry['current'] && $entry['work'] >= $target,
                        'bg-line-strong' => ! $entry['current'] && $entry['work'] < $target,
                    ]) style="height: {{ $height }}%"></div>
            </div>
        @endforeach
    </div>

    <div class="mt-2 flex gap-1.5 text-[10px] text-dim">
        @foreach ($weeks as $entry)
            <span class="metric flex-1 text-center">{{ $entry['start']->isoWeek() }}</span>
        @endforeach
    </div>

    <p class="mt-3 border-t border-line pt-3 text-[11px] text-faint">
        {{ __('app.stats.target', ['hours' => (int) round($target / 3600)]) }}
    </p>
</x-card>
