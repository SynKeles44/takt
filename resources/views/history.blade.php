@use('App\Support\Duration')

<x-app-layout :title="__('app.history.title')">
    @php
        $daysWithEntries = $days->filter(fn (array $day): bool => $day['totals']['gross'] > 0)->count();
        $average = $daysWithEntries > 0 ? (int) round($weekTotals['work'] / $daysWithEntries) : 0;
        $navLink = 'btn btn-ghost';
    @endphp

    <x-card class="rise">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">
                    {{ $weekStart->isoFormat('D. MMM') }} – {{ $weekEnd->isoFormat('D. MMM YYYY') }}
                </h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.history.week_label', ['week' => $weekStart->isoWeek()]) }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('insights', ['zeitraum' => 'monat']) }}" class="{{ $navLink }} text-xs">
                    <x-icon name="chart" class="size-4"/>
                    {{ __('app.insights.title') }}
                </a>

                <a href="{{ route('history', ['from' => $previousWeek]) }}" class="{{ $navLink }}" aria-label="{{ __('app.week.previous') }}">
                    <x-icon name="chevron-left" class="size-4"/>
                </a>

                {{-- always here, only inactive: a button that appears moves its neighbours --}}
                @if ($isCurrentWeek)
                    <span class="{{ $navLink }} is-current">{{ __('app.week.current') }}</span>
                @else
                    <a href="{{ route('history') }}" class="{{ $navLink }}">{{ __('app.week.current') }}</a>
                @endif

                <a href="{{ route('history', ['from' => $nextWeek]) }}" class="{{ $navLink }}" aria-label="{{ __('app.week.next') }}">
                    <x-icon name="chevron-right" class="size-4"/>
                </a>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['label' => __('app.history.week_summary'), 'value' => Duration::human($weekTotals['work']), 'tone' => 'text-work-text'],
                ['label' => __('app.type.break'), 'value' => Duration::human($weekTotals['break']), 'tone' => 'text-rest-text'],
                ['label' => __('app.history.days_worked'), 'value' => (string) $daysWithEntries, 'tone' => 'text-accent-text'],
                ['label' => __('app.history.average_day'), 'value' => Duration::human($average), 'tone' => 'text-ink'],
            ] as $summary)
                <div class="tile px-4 py-3">
                    <p class="text-xs text-faint">{{ $summary['label'] }}</p>
                    <p class="mt-1 font-mono text-lg font-bold tabular-nums {{ $summary['tone'] }}">{{ $summary['value'] }}</p>
                </div>
            @endforeach
        </div>
    </x-card>

    <div class="stack mt-5">
        @if ($days->isEmpty())
            <x-card>
                <p class="py-6 text-center text-sm text-faint">{{ __('app.history.empty_week') }}</p>
            </x-card>
        @endif

        @foreach ($days as $day)
            @php
                $progress = min(100, (int) round($day['totals']['work'] / max($dailyTarget, 1) * 100));
                $isToday = $day['date']->isToday();
                $dateKey = $day['date']->toDateString();
                $exemption = $exemptions[$dateKey] ?? null;
                $dayHints = $hints[$dateKey] ?? [];
                $note = $notes[$dateKey] ?? null;
                $exemptTone = match ($exemption['tone'] ?? null) {
                    'accent' => 'border-accent/30 bg-accent/10 text-accent-text',
                    'danger' => 'border-danger/30 bg-danger/10 text-danger-text',
                    'work' => 'border-work/30 bg-work/10 text-work-text',
                    default => 'border-line bg-raised text-muted',
                };
            @endphp

            @if ($day['entries']->isEmpty())
                <div @class([
                        'row flex items-center justify-between gap-3',
                        'border-accent/30 bg-accent/5' => $isToday,
                    ])>
                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="text-sm font-medium {{ $isToday ? 'text-accent-text' : 'text-faint' }}">{{ $day['date']->isoFormat('dddd') }}</span>
                        @if ($exemption)
                            <span class="pill {{ $exemptTone }}">{{ $exemption['label'] }}</span>
                        @endif
                        <span class="font-mono text-xs text-dim tabular-nums">{{ $day['date']->isoFormat('D. MMM') }}</span>
                    </div>
                    <span class="text-xs text-dim">{{ __('app.history.empty_day') }}</span>
                </div>

                @continue
            @endif

            <x-card @class(['border-accent/30' => $isToday])>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-baseline gap-3">
                        <h2 @class([
                                'text-base font-semibold',
                                'text-accent-text' => $isToday,
                                'text-ink' => ! $isToday,
                            ])>{{ $day['date']->isoFormat('dddd') }}</h2>
                        <span class="font-mono text-sm text-faint tabular-nums">{{ $day['date']->isoFormat('D. MMM') }}</span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm sm:gap-4">
                        @if ($exemption)
                            <span class="pill {{ $exemptTone }}">{{ $exemption['label'] }}</span>
                        @endif

                        @if ($dayHints !== [])
                            <span class="pill border-rest/30 bg-rest/10 text-rest-text" title="{{ collect($dayHints)->pluck('text')->implode(' · ') }}">
                                <x-icon name="alert" class="size-3"/>
                                {{ count($dayHints) }}
                            </span>
                        @endif
                        @if ($day['totals']['break'] > 0)
                            <span class="flex items-center gap-1.5 font-mono text-rest-text tabular-nums" title="{{ __('app.type.break') }}">
                                <x-icon name="coffee" class="size-3.5"/>{{ Duration::human($day['totals']['break']) }}
                            </span>
                        @endif
                        <span class="font-mono font-bold tabular-nums {{ $day['totals']['work'] > 0 ? 'text-work-text' : 'text-dim' }}"
                              title="{{ __('app.chart.legend_work') }}">
                            {{ $day['totals']['work'] > 0 ? Duration::human($day['totals']['work']) : '–' }}
                        </span>

                        <form method="POST" action="{{ route('days.destroy', $day['date']->toDateString()) }}" data-live
                              data-confirm="{{ __('app.form.confirm_delete_day') }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="icon-action icon-action-danger"
                                    aria-label="{{ __('app.history.delete_day') }}"
                                    title="{{ __('app.history.delete_day') }}">
                                <x-icon name="trash" class="size-4"/>
                            </button>
                        </form>
                    </div>
                </div>

                @if ($day['totals']['work'] > 0)
                    <div class="mt-3 h-1 overflow-hidden rounded-[var(--radius-pill)] bg-hover">
                        <div class="h-full rounded-[var(--radius-pill)] bg-gradient-to-r from-work to-work-2" style="width: {{ $progress }}%"></div>
                    </div>
                @endif

                @if ($note)
                    <p class="mt-3 rounded-[var(--radius-control)] border border-line bg-raised px-3 py-2 text-xs leading-relaxed text-muted">
                        {{ $note->body }}
                    </p>
                @endif

                @if ($dayHints !== [])
                    <ul class="mt-3 space-y-1">
                        @foreach ($dayHints as $hint)
                            <li class="flex items-start gap-2 text-xs {{ $hint['level'] === 'danger' ? 'text-danger-text' : 'text-rest-text' }}">
                                <x-icon name="alert" class="mt-0.5 size-3 shrink-0"/>{{ $hint['text'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="mt-4 space-y-2">
                    @foreach ($day['entries'] as $entry)
                        <x-entry-row :entry="$entry"/>
                    @endforeach
                </div>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
