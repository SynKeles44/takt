@use('App\Support\Duration')

<x-app-layout :title="__('app.insights.title')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.insights.subtitle_'.$period) }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-period-tabs :active="$period" :anchor="$from->toDateString()"/>

                <div class="tile flex items-center gap-1 p-1">
                    <a href="{{ route('insights', ['zeitraum' => $period, 'stand' => $previous]) }}"
                       class="icon-action" aria-label="{{ __('app.calendar.previous') }}">
                        <x-icon name="chevron-left" class="size-4"/>
                    </a>

                    <a href="{{ route('insights', ['zeitraum' => $period]) }}"
                       @class(['rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition', 'text-dim' => $isCurrent, 'text-muted hover:text-ink' => ! $isCurrent])>
                        {{ __('app.insights.now') }}
                    </a>

                    <a href="{{ route('insights', ['zeitraum' => $period, 'stand' => $next]) }}"
                       class="icon-action" aria-label="{{ __('app.calendar.next') }}">
                        <x-icon name="chevron-right" class="size-4"/>
                    </a>
                </div>

                <x-menu :label="__('app.insights.export')" icon="download">
                    <x-menu-item :href="route('insights.report', ['zeitraum' => $period, 'stand' => $from->toDateString()])"
                                 icon="printer" blank
                                 :title="__('app.insights.report')"
                                 :text="__('app.insights.report_hint')"/>

                    @if ($period === 'monat')
                        <x-menu-item :href="route('month.timesheet', ['monat' => $month])"
                                     icon="list-check" blank
                                     :title="__('app.month.timesheet')"
                                     :text="__('app.insights.timesheet_hint')"/>

                        <x-menu-item :href="route('month.csv', ['monat' => $month])"
                                     icon="download"
                                     title="CSV"
                                     :text="__('app.insights.csv_hint')"/>
                    @endif
                </x-menu>

            </div>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach ([
                ['label' => __('app.chart.legend_work'), 'value' => Duration::human($work), 'tone' => 'text-work-text', 'foot' => __('app.insights.break_foot', ['value' => Duration::human($break)])],
                ['label' => __('app.month.target'), 'value' => Duration::human($target), 'tone' => 'text-ink', 'foot' => __('app.insights.days_foot', ['count' => $bookedDays])],
                ['label' => __('app.stats.balance'), 'value' => Duration::signed($balance), 'tone' => $balance < 0 ? 'text-danger-text' : 'text-work-text', 'foot' => __('app.insights.average_foot', ['value' => $average > 0 ? Duration::human($average) : '–'])],
                ['label' => __('app.insights.done'), 'value' => (string) $completedCount, 'tone' => 'text-accent-text', 'foot' => __('app.insights.longest_foot', ['value' => $longest > 0 ? Duration::human($longest) : '–'])],
            ] as $tile)
                <div class="tile px-4 py-3">
                    <p class="text-xs text-faint">{{ $tile['label'] }}</p>
                    <p class="metric mt-1 text-base font-bold {{ $tile['tone'] }}">{{ $tile['value'] }}</p>
                    <p class="mt-1 truncate text-[11px] text-muted">{{ $tile['foot'] }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1.2fr_1fr]">
        <x-card class="rise">
            <div class="flex items-center justify-between gap-3">
                <h2 class="heading">{{ __('app.insights.distribution') }}</h2>
                <span class="pill text-[10px]">{{ __('app.insights.target_marker') }}</span>
            </div>

            <div @class([
                    'mt-4 space-y-1.5',
                    'max-h-[24rem] overflow-y-auto pr-1' => count($buckets) > 14,
                 ])>
                @foreach ($buckets as $bucket)
                    <div @class(['row flex items-center gap-3 px-3 py-2', 'ring-1 ring-accent/40' => $bucket['today']])>
                        <div class="w-16 shrink-0">
                            <p class="text-xs font-semibold text-ink">{{ $bucket['label'] }}</p>
                            <p class="text-[11px] text-muted">{{ $bucket['sub'] }}</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="bar relative h-2.5 w-full overflow-hidden bg-hover">
                                <div class="h-full bg-work transition-[width] duration-500"
                                     style="width: {{ $bucket['work'] > 0 ? max(3, round($bucket['work'] / $peak * 100)) : 0 }}%"></div>

                                @if ($bucket['target'] > 0)
                                    <span class="absolute inset-y-0 w-px bg-ink/40"
                                          style="left: {{ min(100, round($bucket['target'] / $peak * 100)) }}%"></span>
                                @endif
                            </div>

                            @if ($bucket['note'])
                                <p class="mt-1 truncate text-[11px] text-{{ $bucket['tone'] ?? 'muted' }}-text">{{ $bucket['note'] }}</p>
                            @endif
                        </div>

                        <p class="metric w-16 shrink-0 text-right text-xs font-semibold {{ $bucket['work'] > 0 ? 'text-ink' : 'text-dim' }}">
                            {{ $bucket['work'] > 0 ? Duration::human($bucket['work']) : '–' }}
                        </p>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card class="rise">
            <div class="flex items-center justify-between gap-3">
                <h2 class="heading">{{ __('app.insights.completed') }}</h2>
                <span class="metric text-xs text-dim">{{ $completedCount }}</span>
            </div>

            @if ($completed->isEmpty())
                <p class="mt-4 text-sm text-dim">{{ __('app.insights.empty_tasks') }}</p>
            @else
                <ul class="mt-4 space-y-2">
                    @foreach ($completed as $todo)
                        <li class="row flex items-center gap-3 px-3 py-2">
                            <x-icon name="check" class="size-4 shrink-0 text-work-text"/>
                            <a href="{{ route('todos.edit', $todo) }}" class="min-w-0 flex-1 truncate text-sm text-ink hover:text-accent-text">
                                {{ $todo->title }}
                            </a>
                            <span class="shrink-0 text-[11px] text-dim">
                                {{ $todo->completed_at?->isoFormat($period === 'woche' ? 'dd HH:mm' : 'D. MMM') }}
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($completedCount > $completed->count())
                    <p class="mt-3 text-[11px] text-dim">{{ __('app.insights.more', ['count' => $completedCount - $completed->count()]) }}</p>
                @endif
            @endif
        </x-card>
    </div>

    @if ($heatmap !== null)
        <x-card class="rise mt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="heading">{{ __('app.insights.heatmap') }}</h2>

                <div class="flex items-center gap-1.5 text-[11px] text-dim">
                    <span>{{ __('app.insights.less') }}</span>
                    @foreach (['bg-hover', 'bg-work/25', 'bg-work/45', 'bg-work/70', 'bg-work'] as $swatch)
                        <span class="size-3 rounded-[3px] {{ $swatch }}"></span>
                    @endforeach
                    <span>{{ __('app.insights.more_legend') }}</span>
                </div>
            </div>

            <div class="mt-4 overflow-x-auto pb-1">
                <div class="flex min-w-max gap-[3px]">
                    @foreach ($heatmap as $week)
                        <div class="flex flex-col gap-[3px]">
                            @foreach ($week as $day)
                                @php
                                    $ratio = $dailyTarget > 0 ? $day['work'] / $dailyTarget : 0;
                                    $level = match (true) {
                                        $day['work'] === 0 => 'bg-hover',
                                        $ratio < 0.5 => 'bg-work/25',
                                        $ratio < 0.85 => 'bg-work/45',
                                        $ratio < 1.05 => 'bg-work/70',
                                        default => 'bg-work',
                                    };
                                @endphp
                                <span @class(['size-3 rounded-[3px]', $level, 'opacity-25' => ! $day['inRange']])
                                      title="{{ $day['date']->isoFormat('dd, D. MMM YYYY') }} · {{ $day['work'] > 0 ? Duration::human($day['work']) : '–' }}"></span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </x-card>
    @endif
</x-app-layout>
