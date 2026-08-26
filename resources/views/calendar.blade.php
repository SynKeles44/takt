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

                <a href="{{ route('calendar', ['monat' => $previousMonth]) }}" class="btn btn-icon" aria-label="{{ __('app.calendar.previous') }}">
                    <x-icon name="chevron-left" class="size-4"/>
                </a>

                @if ($isCurrentMonth)
                    <span class="btn btn-ghost is-current text-xs">{{ __('app.calendar.today') }}</span>
                @else
                    <a href="{{ route('calendar') }}" class="btn btn-ghost text-xs">{{ __('app.calendar.today') }}</a>
                @endif

                <a href="{{ route('calendar', ['monat' => $nextMonth]) }}" class="btn btn-icon" aria-label="{{ __('app.calendar.next') }}">
                    <x-icon name="chevron-right" class="size-4"/>
                </a>
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
            <form method="POST" action="{{ route('absences.store') }}" data-live data-absence-form>
                @csrf

                <div class="flex items-start gap-3 border-b border-line px-5 py-4 sm:px-6">
                    <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text">
                        <x-icon name="calendar-days" class="size-4"/>
                    </span>
                    <div class="min-w-0">
                        <h2 id="absence-dialog-title" class="text-sm font-semibold text-ink">{{ __('app.absence.new') }}</h2>
                        <p class="metric mt-0.5 truncate text-xs text-muted" data-absence-range></p>
                    </div>
                </div>

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
        </div>
    </div>
</x-app-layout>
