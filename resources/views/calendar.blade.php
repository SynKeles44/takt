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

                @unless ($isCurrentMonth)
                    <a href="{{ route('calendar') }}" class="btn btn-ghost text-xs">{{ __('app.calendar.today') }}</a>
                @endunless

                <a href="{{ route('calendar', ['monat' => $nextMonth]) }}" class="btn btn-icon" aria-label="{{ __('app.calendar.next') }}">
                    <x-icon name="chevron-right" class="size-4"/>
                </a>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-7 gap-1 sm:gap-1.5">
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
                            'neutral' => 'bg-muted',
                            default => '',
                        };
                    @endphp

                    <a href="{{ route('history', ['from' => $day['date']->copy()->startOfWeek()->toDateString()]) }}"
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
</x-app-layout>
