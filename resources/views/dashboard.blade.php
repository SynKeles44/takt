@use('App\Enums\EntryType')
@use('App\Support\Duration')

<x-app-layout :title="__('app.nav.dashboard')">
    @php
        $isWork = $running?->type === EntryType::Work;
        $runningSeconds = $running?->durationInSeconds() ?? 0;
        $btnWork = 'btn btn-work btn-lg';
        $btnBreak = 'btn btn-rest btn-lg';
        $btnGhost = 'btn btn-ghost btn-lg';
    @endphp

    @if ($exemption)
        @php
            $exemptTone = match ($exemption['tone']) {
                'accent' => 'border-accent/30 bg-accent/10 text-accent-text',
                'danger' => 'border-danger/30 bg-danger/10 text-danger-text',
                'work' => 'border-work/30 bg-work/10 text-work-text',
                default => 'border-line bg-raised text-muted',
            };
        @endphp
        <div class="mb-4 flex items-center gap-2.5 rounded-[var(--radius-control)] border px-4 py-3 text-sm font-medium {{ $exemptTone }}">
            <x-icon name="calendar-days" class="size-4 shrink-0"/>
            {{ __('app.absence.today', ['label' => $exemption['label']]) }}
        </div>
    @endif

    @if ($hints !== [])
        <div class="mb-4 space-y-2">
            @foreach ($hints as $hint)
                <div @class([
                        'flex items-start gap-2.5 rounded-[var(--radius-control)] border px-4 py-2.5 text-xs',
                        'border-danger/30 bg-danger/10 text-danger-text' => $hint['level'] === 'danger',
                        'border-rest/30 bg-rest/10 text-rest-text' => $hint['level'] !== 'danger',
                    ])>
                    <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0"/>
                    <span>{{ $hint['text'] }}</span>
                </div>
            @endforeach
        </div>
    @endif

    <x-card class="rise relative overflow-hidden">
        <div aria-hidden="true"
             class="pointer-events-none absolute -right-24 -top-28 size-72 rounded-full blur-3xl {{ $running === null ? 'bg-accent/10' : ($isWork ? 'bg-work/15' : 'bg-rest/15') }}"></div>

        <div class="relative flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                @if ($running)
                    <div class="flex items-center gap-3">
                                <span class="pulse-ring dot relative flex size-2.5 {{ $isWork ? 'bg-work text-work' : 'bg-rest text-rest' }}"></span>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] {{ $isWork ? 'text-work-text' : 'text-rest-text' }}">
                            {{ $running->type->label() }}
                        </p>
                    </div>

                    <p class="metric mt-3 text-5xl font-bold tracking-tight text-ink sm:text-6xl"
                       data-since="{{ $running->started_at->toIso8601String() }}">
                        {{ Duration::clock($running->durationInSeconds()) }}
                    </p>

                    <p class="mt-2 text-sm text-muted">
                        {{ __('app.timer.running_since', ['time' => $running->started_at->format('H:i')]) }}
                        @if ($running->note)
                            <span class="text-dim">·</span> {{ $running->note }}
                        @endif
                    </p>
                @else
                    <div class="flex items-center gap-3">
                        <span class="dot flex size-2.5 bg-dim"></span>
                        <p class="text-sm font-semibold uppercase tracking-[0.18em] text-faint">
                            {{ __('app.timer.idle_title') }}
                        </p>
                    </div>

                    <p class="metric mt-3 text-5xl font-bold tracking-tight text-dim sm:text-6xl">
                        00:00:00
                    </p>

                    <p class="mt-2 text-sm text-muted">{{ __('app.timer.idle_hint') }}</p>
                @endif
            </div>

            <div class="flex shrink-0 flex-col gap-2.5 sm:flex-row">
                @if ($running === null)
                    <form method="POST" action="{{ route('timer.start') }}" data-live>
                        @csrf
                        <input type="hidden" name="type" value="{{ EntryType::Work->value }}">
                        <button type="submit" class="{{ $btnWork }} w-full">
                            <x-icon name="play" class="size-4"/>
                            {{ __('app.timer.start_work') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('timer.start') }}" data-live>
                        @csrf
                        <input type="hidden" name="type" value="{{ EntryType::Break->value }}">
                        <button type="submit" class="{{ $btnGhost }} w-full">
                            <x-icon name="coffee" class="size-4"/>
                            {{ __('app.timer.start_break') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('timer.start') }}" data-live>
                        @csrf
                        <input type="hidden" name="type" value="{{ $running->type->opposite()->value }}">
                        <button type="submit" class="{{ $isWork ? $btnBreak : $btnWork }} w-full">
                            <x-icon :name="$isWork ? 'pause' : 'play'" class="size-4"/>
                            {{ $isWork ? __('app.timer.to_break') : __('app.timer.back_to_work') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('timer.stop') }}" data-live>
                        @csrf
                        <button type="submit" class="{{ $btnGhost }} w-full">
                            <x-icon name="stop" class="size-4"/>
                            {{ __('app.timer.end_day') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid sm:grid-cols-2 lg:grid-cols-4">
        @php
            $remaining = $dailyTarget - $todayTotals['work'];
        @endphp

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
                :hint="__('app.stats.balance_hint', [
                    'hours' => Duration::signedDecimal($balance['seconds']),
                    'days' => $balance['days'],
                ])">
            <x-slot:icon><x-icon name="scale" class="size-4"/></x-slot:icon>
        </x-stat>
    </div>

    <div class="stack-grid mt-6 grid lg:grid-cols-5">
        <div class="stack lg:col-span-3">
            <x-card>
                @php
                    $weekNav = 'btn btn-icon';
                @endphp

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
                        <a href="{{ route('dashboard', ['woche' => $previousWeek]) }}" class="{{ $weekNav }}"
                           aria-label="{{ __('app.week.previous') }}" title="{{ __('app.week.previous') }}">
                            <x-icon name="chevron-left" class="size-4"/>
                        </a>

                        @unless ($isCurrentWeek)
                            <a href="{{ route('dashboard') }}"
                               class="btn btn-icon border-accent/30 bg-accent/10 px-2.5 text-xs text-accent-text">
                                {{ __('app.week.current') }}
                            </a>
                        @endunless

                        <a href="{{ route('dashboard', ['woche' => $nextWeek]) }}" class="{{ $weekNav }}"
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

                            <span @class([
                                    'text-[11px] font-semibold uppercase',
                                    'text-accent-text' => $isToday,
                                    'text-faint' => ! $isToday,
                                ])>{{ $day['date']->isoFormat('dd') }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center gap-4 border-t border-line pt-3 text-xs text-faint">
                    <span class="flex items-center gap-1.5"><span class="dot size-2 bg-work"></span>{{ __('app.chart.legend_work') }}</span>
                    <span class="flex items-center gap-1.5"><span class="dot size-2 bg-rest"></span>{{ __('app.chart.legend_break') }} {{ Duration::human($chartTotals['break']) }}</span>
                </div>
            </x-card>


            <x-card>
                @php
                    $urgent = $todos->filter(fn ($todo) => $todo->dueState()->isUrgent());
                @endphp

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="heading">{{ __('app.todos.dashboard_title') }}</h2>
                    <div class="flex items-center gap-2">
                        @if ($urgent->isNotEmpty())
                            <span class="pill border-danger/40 bg-danger/15 text-danger-text">
                                <x-icon name="alert" class="size-3.5"/>
                                {{ trans_choice('app.todos.urgent_count', $urgent->count()) }}
                            </span>
                        @endif
                        <a href="{{ route('todos.index') }}" class="pill hover:text-ink">{{ __('app.todos.open_all') }}</a>
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($todos->take(6) as $todo)
                        <x-todo-row :todo="$todo" compact/>
                    @empty
                        <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
                            {{ __('app.todos.empty') }}
                        </p>
                    @endforelse
                </div>

                @if ($todos->count() > 6)
                    <p class="mt-3 text-center text-xs text-faint">
                        {{ trans_choice('app.todos.more', $todos->count() - 6) }}
                    </p>
                @endif
            </x-card>

            <x-card>
                <div class="flex items-center justify-between gap-3">
                    <h2 class="heading">{{ __('app.entries.today_title') }}</h2>
                    <span class="pill">{{ trans_choice('app.entries.count', $entries->count()) }}</span>
                </div>

                <div class="mt-4 space-y-2">
                    @forelse ($entries as $entry)
                        <x-entry-row :entry="$entry"/>
                    @empty
                        <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-8 text-center text-sm text-faint">
                            {{ __('app.entries.empty') }}
                        </p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="stack lg:col-span-2">
            <x-card>
                <form method="POST" action="{{ route('notes.store') }}" class="space-y-3" data-live>
                    @csrf
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.notes.title') }}</h2>
                        <span class="pill">{{ $today->isoFormat('D. MMM') }}</span>
                    </div>

                    <input type="hidden" name="day" value="{{ $today->toDateString() }}">
                    <textarea name="body" rows="4" class="control" maxlength="2000"
                              placeholder="{{ __('app.notes.placeholder') }}">{{ old('body', $dayNote?->body) }}</textarea>
                    @error('body') <p class="field-error">{{ $message }}</p> @enderror

                    <button type="submit" class="btn btn-ghost w-full text-xs">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.notes.save') }}
                    </button>
                </form>
            </x-card>

            <x-card class="lg:sticky lg:top-8">
                <div class="flex items-start gap-3">
                    <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="plus" class="size-4"/></span>
                    <div>
                        <h2 class="text-sm font-semibold text-ink">{{ __('app.entries.manual_title') }}</h2>
                        <p class="text-xs text-faint">{{ __('app.entries.manual_hint') }}</p>
                    </div>
                </div>

                <div class="mt-5">
                    <x-booking-form :open-todos="$openTodos"/>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
