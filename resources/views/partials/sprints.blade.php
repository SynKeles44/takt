@use('App\Support\Duration')

{{--
    The sprint view.

    Linear's own cycle panel answers "how is the sprint doing" from the tickets alone. This one has
    something Linear cannot have: the hours. So every sprint carries both — what was closed, and
    what it cost — and the chart is a record of days actually worked rather than a burndown. A
    burndown would need the scope as it stood on each day, and the API gives the scope as it stands
    now; drawing one from today's numbers is a straight line pretending to be a measurement.
--}}
<div class="stack">
    @if (! $sprints['configured'])
        <x-card>
            <p class="text-sm text-dim">{{ __('app.tickets.no_token') }}</p>
            <a href="{{ route('settings') }}" class="btn btn-ghost mt-3 text-xs">
                <x-icon name="gear" class="size-3.5"/>
                {{ __('app.linear.token') }}
            </a>
        </x-card>
    @elseif ($sprints['sprints']->isEmpty())
        <x-card>
            <x-empty pose="search" :title="__('app.sprint.empty')" :hint="__('app.sprint.empty_hint')"/>
        </x-card>
    @else
        @foreach ($sprints['sprints'] as $sprint)
            @php
                $peak = max(1, collect($sprint['days'])->max('seconds'));
                $share = $sprint['scope'] > 0 ? round($sprint['done'] / $sprint['scope'] * 100) : 0;
            @endphp

            <x-card @class(['rise', 'border-accent/40' => $sprint['current']])>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h2 class="flex items-center gap-2 text-base font-semibold text-ink">
                            <x-icon name="repeat" class="size-4 shrink-0 text-dim"/>
                            {{ $sprint['name'] ?: __('app.sprint.number', ['number' => $sprint['number']]) }}

                            @if ($sprint['current'])
                                <span class="pill border-accent/40 bg-accent/10 text-[10px] text-accent-text">{{ __('app.sprint.current') }}</span>
                            @endif
                        </h2>

                        <p class="metric mt-0.5 text-xs text-faint">
                            {{ $sprint['from']->isoFormat('D. MMM') }} – {{ $sprint['to']->isoFormat('D. MMM YYYY') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ([
                            [__('app.sprint.scope'), (string) $sprint['scope'], 'text-ink'],
                            [__('app.sprint.started'), (string) $sprint['started'], 'text-accent-text'],
                            [__('app.sprint.done'), (string) $sprint['done'], 'text-work-text'],
                            [__('app.sprint.booked'), Duration::human($sprint['seconds_in_window']), 'text-ink'],
                        ] as [$label, $value, $tone])
                            <div class="tile px-3 py-2 text-center">
                                <p class="metric text-lg font-bold {{ $tone }}">{{ $value }}</p>
                                <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <div class="h-1.5 min-w-40 flex-1 overflow-hidden rounded-[var(--radius-pill)] bg-raised">
                        <div class="h-full rounded-[var(--radius-pill)] bg-gradient-to-r from-work to-work-2"
                             style="width: {{ $share }}%"></div>
                    </div>

                    <span class="metric text-[11px] text-faint">{{ $share }}%</span>

                    @if ($sprint['points'] > 0)
                        <span class="pill text-[10px]" title="{{ __('app.sprint.points_hint') }}">
                            {{ __('app.sprint.points', ['done' => $sprint['points_done'], 'total' => $sprint['points']]) }}
                        </span>
                    @endif

                    {{-- two measurements, not one: the gap between them is where the sprint's time actually went --}}
                    <span class="pill text-[10px]" title="{{ __('app.sprint.on_issues_hint') }}">
                        {{ __('app.sprint.on_issues', ['duration' => Duration::human($sprint['seconds_on_issues'])]) }}
                    </span>
                </div>

                {{-- one bar per day: hours worked, with a dot for every ticket closed that day --}}
                <div class="mt-5 flex items-end gap-[3px]" style="block-size: 7rem">
                    @foreach ($sprint['days'] as $day)
                        <div class="group relative flex h-full flex-1 flex-col justify-end"
                             title="{{ $day['date']->isoFormat('dd, D. MMM') }} · {{ Duration::human($day['seconds']) }}{{ $day['closed'] > 0 ? ' · '.trans_choice('app.sprint.closed_count', $day['closed']) : '' }}">
                            @if ($day['closed'] > 0)
                                <span class="mx-auto mb-1 flex flex-col items-center gap-0.5">
                                    @for ($i = 0; $i < min($day['closed'], 3); $i++)
                                        <span class="block size-1 rounded-full bg-work"></span>
                                    @endfor
                                </span>
                            @endif

                            <div @class([
                                    'w-full rounded-[3px] transition',
                                    'bg-gradient-to-t from-work to-work-2' => $day['seconds'] > 0,
                                    'bg-line/60' => $day['seconds'] === 0 && ! $day['weekend'] && ! $day['future'],
                                    'bg-raised' => $day['seconds'] === 0 && ($day['weekend'] || $day['future']),
                                 ])
                                 style="block-size: {{ $day['seconds'] > 0 ? max(4, round($day['seconds'] / $peak * 100)) : 3 }}%"></div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-1.5 flex items-center justify-between text-[10px] text-faint">
                    <span>{{ $sprint['from']->isoFormat('D. MMM') }}</span>
                    <span>{{ $sprint['to']->isoFormat('D. MMM') }}</span>
                </div>

                <details class="mt-4 border-t border-line pt-3">
                    <summary class="cursor-pointer text-xs font-medium text-muted hover:text-ink">
                        {{ trans_choice('app.sprint.issue_count', $sprint['scope']) }}
                    </summary>

                    <div class="mt-3 space-y-1.5">
                        @foreach ($sprint['issues'] as $issue)
                            <a href="{{ route('tickets.show', ['key' => $issue['id']]) }}" class="row flex items-center gap-3 px-3 py-2">
                                <span @class([
                                        'size-2 shrink-0 rounded-full',
                                        'bg-work' => $issue['state_type'] === 'completed',
                                        'bg-accent' => $issue['state_type'] === 'started',
                                        'bg-line-strong' => ! in_array($issue['state_type'], ['completed', 'started'], true),
                                     ])></span>

                                <span class="metric shrink-0 text-[11px] text-dim">{{ $issue['id'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $issue['title'] }}</span>

                                @if (($issue['estimate'] ?? null) !== null)
                                    <span class="pill shrink-0 text-[10px]">{{ $issue['estimate'] }}</span>
                                @endif

                                <span class="shrink-0 text-[11px] text-faint">{{ $issue['state'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            </x-card>
        @endforeach
    @endif
</div>
