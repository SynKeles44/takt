@use('App\Support\Duration')

<x-card>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <div class="flex items-baseline gap-3">
            <h2 class="heading">
                {{ $isCurrentWeek ? __('app.chart.title') : __('app.week.short', [
                    'week' => $chartStart->isoWeek(),
                    'from' => $chartStart->isoFormat('D. MMM'),
                    'to' => $chartEnd->isoFormat('D. MMM'),
                ]) }}
            </h2>
            <span class="font-mono text-lg font-bold tabular-nums {{ $chartTotals['work'] > 0 ? 'text-work-text' : 'text-dim' }}">
                {{ Duration::human($chartTotals['work']) }}
            </span>
        </div>

        <div class="flex items-center gap-1.5">
            <a href="{{ route('dashboard', ['woche' => $previousWeek]) }}" class="btn btn-icon"
               aria-label="{{ __('app.week.previous') }}" title="{{ __('app.week.previous') }}">
                <x-icon name="chevron-left" class="size-4"/>
            </a>

            @if ($isCurrentWeek)
                <span class="btn btn-icon is-current px-2.5 text-xs">{{ __('app.week.current') }}</span>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-icon border-accent/30 bg-accent/10 px-2.5 text-xs text-accent-text">
                    {{ __('app.week.current') }}
                </a>
            @endif

            <a href="{{ route('dashboard', ['woche' => $nextWeek]) }}" class="btn btn-icon"
               aria-label="{{ __('app.week.next') }}" title="{{ __('app.week.next') }}">
                <x-icon name="chevron-right" class="size-4"/>
            </a>
        </div>
    </div>

    @php
        $peak = max($dailyTarget, (int) $week->max(fn (array $day): int => $day['totals']['gross']), 1);
        $bar = fn (int $seconds): float => $seconds > 0 ? max(2.5, round($seconds / $peak * 100, 2)) : 0;
    @endphp

    <div class="mt-5 grid grid-cols-7 gap-1.5 sm:gap-2.5">
        @foreach ($week as $day)
            @php
                $isToday = $day['date']->isToday();
                $isFuture = $day['date']->isFuture();
            @endphp
            <div class="flex flex-col items-center gap-2">
                <span class="font-mono text-[10px] tabular-nums {{ $day['totals']['work'] > 0 ? 'text-ink' : 'text-dim' }}">
                    {{ $day['totals']['work'] > 0 ? Duration::decimalHours($day['totals']['work']) : '–' }}
                </span>

                <div @class([
                        'flex h-28 w-full flex-col justify-end gap-[3px] rounded-[var(--radius-control)] border p-[3px] sm:h-32',
                        'border-accent/40 bg-accent/10' => $isToday,
                        'border-line bg-raised' => ! $isToday,
                        'opacity-45' => $isFuture,
                    ])>
                    @if ($day['totals']['break'] > 0)
                        <div class="bar w-full bg-gradient-to-t from-rest to-rest-2"
                             style="height: {{ $bar($day['totals']['break']) }}%"
                             title="{{ __('app.chart.legend_break') }}: {{ Duration::human($day['totals']['break']) }}"></div>
                    @endif
                    @if ($day['totals']['work'] > 0)
                        <div class="bar w-full bg-gradient-to-t from-work to-work-2"
                             style="height: {{ $bar($day['totals']['work']) }}%"
                             title="{{ __('app.chart.legend_work') }}: {{ Duration::human($day['totals']['work']) }}"></div>
                    @endif
                </div>

                <span @class(['text-[11px] font-semibold uppercase', 'text-accent-text' => $isToday, 'text-faint' => ! $isToday])>
                    {{ $day['date']->isoFormat('dd') }}
                </span>
            </div>
        @endforeach
    </div>

    <div class="mt-4 flex items-center gap-4 border-t border-line pt-3 text-xs text-faint">
        <span class="flex items-center gap-1.5"><span class="dot size-2 bg-work"></span>{{ __('app.chart.legend_work') }}</span>
        <span class="flex items-center gap-1.5"><span class="dot size-2 bg-rest"></span>{{ __('app.chart.legend_break') }} {{ Duration::human($chartTotals['break']) }}</span>
    </div>
</x-card>
