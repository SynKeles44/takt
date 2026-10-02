@use('App\Support\Duration')

<x-app-layout :title="__('app.nav.calendar')">
    <x-card class="rise">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ $month->isoFormat('MMMM YYYY') }}</h2>
                <p class="mt-0.5 text-xs text-faint">
                    {{ __('app.calendar.month_work', ['duration' => Duration::human($monthWork)]) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="pill" title="{{ __('app.absence.vacation_title') }}">
                    {{ __('app.absence.remaining') }}: {{ rtrim(rtrim(number_format($vacation['remaining'], 1, ',', ''), '0'), ',') }}
                </span>

                <a href="{{ route('absences') }}" class="btn btn-ghost text-xs">
                    <x-icon name="calendar-days" class="size-4"/>
                    {{ __('app.absence.title') }}
                </a>

                <x-date-nav marker-key="calendar-month"
                            :position="$isCurrentMonth ? 'now' : ($month->isBefore(today()->startOfMonth()) ? 'past' : 'future')"
                            :previous="route('calendar', ['monat' => $previousMonth])"
                            :current="route('calendar')"
                            :next="route('calendar', ['monat' => $nextMonth])"
                            :label="__('app.calendar.today')"
                            :previous-label="__('app.calendar.previous')"
                            :next-label="__('app.calendar.next')"/>
            </div>
        </div>

        <p class="mt-4 text-[11px] text-dim">{{ __('app.absence.select_hint') }}</p>

        <div class="mt-2 grid grid-cols-7 gap-1 select-none sm:gap-1.5" data-day-picker>
            @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $weekday)
                <span class="heading pb-1 text-center">{{ $weekday }}</span>
            @endforeach

            @foreach ($weeks as $week)
                @foreach ($week as $day)
                    @php
                        $isToday = $day['date']->isToday();
                        $reached = $dailyTarget > 0 && $day['work'] >= $dailyTarget;
                        $exemption = $day['exemption'];
                        $exemptDot = match ($exemption['tone'] ?? null) {
                            'accent' => 'bg-accent',
                            'danger' => 'bg-danger',
                            'work' => 'bg-work',
                            'rest' => 'bg-rest',
                            'neutral' => 'bg-muted',
                            default => '',
                        };
                    @endphp

                    <a href="{{ route('history', ['from' => $day['date']->copy()->startOfWeek()->toDateString()]) }}"
                       data-day="{{ $day['date']->toDateString() }}"
                       data-day-label="{{ $day['date']->isoFormat('dd, D. MMM YYYY') }}"
                       draggable="false"
                       @class([
                           'flex min-h-20 flex-col gap-1 rounded-[var(--radius-control)] border p-1.5 transition sm:min-h-24 sm:p-2',
                           'border-accent/50 bg-accent/10' => $isToday,
                           'border-line bg-raised hover:border-line-strong' => ! $isToday,
                           'opacity-45' => ! $day['inMonth'],
                           'border-dashed' => $exemption !== null,
                       ])
                       title="{{ $day['date']->isoFormat('dddd, D. MMMM') }}">
                        <span class="flex items-center justify-between gap-1">
                            <span @class(['metric text-xs', 'font-bold text-accent-text' => $isToday, 'text-muted' => ! $isToday])>
                                {{ $day['date']->format('j') }}
                            </span>

                            @if ($day['work'] > 0)
                                <span @class(['metric text-[10px]', 'text-work-text' => $reached, 'text-muted' => ! $reached])>
                                    {{ Duration::compact($day['work']) }}
                                </span>
                            @endif
                        </span>

                        @if ($exemption)
                            <span class="flex items-center gap-1">
                                <span class="dot size-1.5 shrink-0 {{ $exemptDot }}"></span>
                                <span class="truncate text-[10px] text-muted">{{ $exemption['label'] }}</span>
                            </span>
                        @endif

                        @if ($day['work'] > 0)
                            <span class="h-1 overflow-hidden rounded-[var(--radius-pill)] bg-hover">
                                <span class="block h-full rounded-[var(--radius-pill)] bg-gradient-to-r from-work to-work-2"
                                      style="width: {{ min(100, $dailyTarget > 0 ? (int) round($day['work'] / $dailyTarget * 100) : 100) }}%"></span>
                            </span>
                        @endif

                        <span class="flex min-w-0 flex-col gap-0.5">
                            @foreach ($day['todos']->take(2) as $todo)
                                <span class="flex items-center gap-1">
                                    <span class="dot size-1.5 shrink-0 {{ $todo->dueState()->dotClass() }}"></span>
                                    <span @class(['truncate text-[10px]', 'text-faint line-through' => $todo->isDone(), 'text-ink' => ! $todo->isDone()])>
                                        {{ $todo->title }}
                                    </span>
                                </span>
                            @endforeach

                            @if ($day['todos']->count() > 2)
                                <span class="text-[10px] text-dim">+{{ $day['todos']->count() - 2 }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            @endforeach
        </div>
    </x-card>

    {{-- opens as soon as the mouse is let go over the marked days --}}
    <div class="pointer-events-none fixed inset-0 z-[70] hidden items-center justify-center p-4"
         data-absence-dialog role="dialog" aria-modal="true" aria-labelledby="absence-dialog-title">
        <div class="absolute inset-0 bg-canvas/75 backdrop-blur-sm" data-absence-cancel></div>

        <div class="surface-plain dialog-panel pointer-events-auto relative w-full max-w-md p-0">
            {{-- one selection of days, two things you might want to do with it --}}
            <div class="flex items-start gap-3 border-b border-line px-5 py-4 sm:px-6">
                <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text">
                    <x-icon name="calendar-days" class="size-4"/>
                </span>
                <div class="min-w-0 flex-1">
                    <h2 id="absence-dialog-title" class="text-sm font-semibold text-ink">{{ __('app.absence.new') }}</h2>
                    <p class="metric mt-0.5 truncate text-xs text-muted" data-absence-range></p>
                </div>
            </div>

            <div class="px-5 pt-4 sm:px-6">
                <div class="segmented" data-dialog-mode>
                    <button type="button" class="segment segment-active" data-mode="absence">{{ __('app.absence.new') }}</button>
                    <button type="button" class="segment" data-mode="bulk">{{ __('app.bulk.title') }}</button>
                </div>
            </div>

            <form method="POST" action="{{ route('absences.store') }}" data-live data-absence-form data-mode-panel="absence">
                @csrf

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <input type="hidden" name="starts_on" data-absence-start>
                    <input type="hidden" name="ends_on" data-absence-end>

                    <div>
                        <span class="label">{{ __('app.form.type') }}</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($types as $type)
                                <label class="block">
                                    <input type="radio" name="type" value="{{ $type->value }}" class="peer sr-only"
                                           @checked($type->value === 'vacation')>
                                    <span class="control flex cursor-pointer items-center justify-center text-center text-xs font-medium text-muted transition peer-checked:border-accent/50 peer-checked:bg-accent/10 peer-checked:text-accent-text">
                                        {{ $type->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label for="absence-note" class="label">{{ __('app.form.note') }}</label>
                        <input id="absence-note" type="text" name="note" class="control" maxlength="200"
                               placeholder="{{ __('app.absence.note_placeholder') }}">
                    </div>
                </div>

                <div class="flex gap-2 border-t border-line px-5 py-4 sm:px-6">
                    <button type="button" class="btn btn-ghost flex-1" data-absence-cancel>
                        {{ __('app.dialog.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary flex-1">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.absence.save') }}
                    </button>
                </div>
            </form>

            {{--
                The same days, filled with working time instead. The scatter is what keeps a month
                of entries from reading as typed in: each day gets its own offset, and both ends of
                a stretch move independently.
            --}}
            <form method="POST" action="{{ route('entries.bulk') }}" data-live data-mode-panel="bulk" class="hidden">
                @csrf

                <div class="space-y-4 px-5 py-5 sm:px-6">
                    <div data-bulk-days></div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="label">{{ __('app.form.start') }}</span>
                            <x-time-field name="von" value="09:00" required class="mt-1 w-full"/>
                        </label>
                        <label class="block">
                            <span class="label">{{ __('app.form.end') }}</span>
                            <x-time-field name="bis" value="17:00" required class="mt-1 w-full"/>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <label class="block">
                            <span class="label">{{ __('app.bulk.break_from') }}</span>
                            <x-time-field name="pause_von" value="12:30" class="mt-1 w-full"/>
                        </label>
                        <label class="block">
                            <span class="label">{{ __('app.bulk.break_to') }}</span>
                            <x-time-field name="pause_bis" value="13:00" class="mt-1 w-full"/>
                        </label>
                    </div>

                    <label class="block">
                        <span class="flex items-baseline justify-between">
                            <span class="label">{{ __('app.bulk.scatter') }}</span>
                            <span class="metric text-xs text-accent-text" data-scatter-value>15 min</span>
                        </span>
                        <input type="range" name="streuung" min="0" max="60" step="5" value="15"
                               class="mt-2 w-full accent-[var(--color-accent)]" data-scatter>
                        <span class="mt-1 block text-[11px] leading-snug text-faint">{{ __('app.bulk.scatter_hint') }}</span>
                    </label>

                    <div class="space-y-2 border-t border-line pt-3">
                        <label class="flex items-start gap-2.5">
                            <input type="hidden" name="ueberschreiben" value="0">
                            <input type="checkbox" name="ueberschreiben" value="1" class="mt-0.5">
                            <span class="text-xs text-muted">{{ __('app.bulk.overwrite') }}</span>
                        </label>

                        <label class="flex items-start gap-2.5">
                            <input type="hidden" name="feiertage" value="0">
                            <input type="checkbox" name="feiertage" value="1" class="mt-0.5">
                            <span class="text-xs text-muted">{{ __('app.bulk.include_exempt') }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-2 border-t border-line px-5 py-4 sm:px-6">
                    <button type="button" class="btn btn-ghost flex-1" data-absence-cancel>
                        {{ __('app.dialog.cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary flex-1">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.bulk.save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    <x-mascot pose="calendar" class="mascot-at-tail size-20"/>
</x-app-layout>
