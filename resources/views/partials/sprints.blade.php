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
            @php $share = $sprint['scope'] > 0 ? round($sprint['done'] / $sprint['scope'] * 100) : 0; @endphp

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

                    {{--
                        The legend is the figures, the way Linear's is: the dot in front of a number
                        is the same colour as the curve it belongs to, so the chart needs no key of
                        its own and the numbers are not a second, separate thing to read.
                    --}}
                    <div class="flex flex-wrap items-start gap-5">
                        @foreach ([
                            [__('app.sprint.scope'), $sprint['scope'], 'bg-line-strong', null],
                            [__('app.sprint.started'), $sprint['started'], 'bg-accent', $sprint['scope'] > 0 ? round($sprint['started'] / $sprint['scope'] * 100) : 0],
                            [__('app.sprint.done'), $sprint['done'], 'bg-work', $share],
                            [__('app.sprint.booked'), Duration::human($sprint['seconds_in_window']), null, null],
                        ] as [$label, $value, $dot, $percent])
                            <div class="min-w-16">
                                <p class="flex items-center gap-1.5 text-[10px] uppercase tracking-wide text-faint">
                                    @if ($dot)<span class="size-1.5 rounded-[2px] {{ $dot }}"></span>@endif
                                    {{ $label }}
                                </p>
                                <p class="metric mt-0.5 flex items-baseline gap-1.5">
                                    <span class="text-lg font-bold text-ink">{{ $value }}</span>
                                    @if ($percent !== null)<span class="text-[11px] text-dim">{{ $percent }}%</span>@endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>

                <x-sprint-chart :days="$sprint['days']" class="mt-5"/>

                <div class="mt-1.5 flex items-center justify-between text-[10px] text-faint">
                    <span>{{ $sprint['from']->isoFormat('D. MMM') }}</span>

                    <span class="flex items-center gap-3">
                        @if ($sprint['points'] > 0)
                            <span class="pill text-[10px]" title="{{ __('app.sprint.points_hint') }}">
                                {{ __('app.sprint.points', ['done' => $sprint['points_done'], 'total' => $sprint['points']]) }}
                            </span>
                        @endif

                        <span class="pill text-[10px]" title="{{ __('app.sprint.on_issues_hint') }}">
                            {{ __('app.sprint.on_issues', ['duration' => Duration::human($sprint['seconds_on_issues'])]) }}
                        </span>
                    </span>

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
