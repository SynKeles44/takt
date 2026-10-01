@use('App\Support\Duration')

{{--
    The board the way Linear draws a cycle: one column per workflow state, grouped by project
    inside, with the sprint standing beside it.

    Its columns are the team's, which is what makes it different from the other board — dropping a
    card here writes the state back to Linear. The day board next to it has five columns that are
    mine and writes nothing anywhere. Two boards, because they answer two questions.
--}}
{{--
    Which columns to draw. Every state of every team is offered, including the ones nothing sits in
    today — an empty column says where things go next, and a board that only draws what it happens
    to be occupying rearranges itself as you work.
--}}
@if ($allStates !== [])
    <details class="mb-3">
        <summary class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-muted hover:text-ink">
            <x-icon name="chevron-right" class="size-3"/>
            {{ __('app.ticket.pick_states') }}
            <span class="pill text-[10px]">{{ $visibleStates === null ? __('app.tickets.filter_all') : count($visibleStates) }}</span>
        </summary>

        <form method="POST" action="{{ route('tickets.states') }}" class="mt-2 flex flex-wrap items-center gap-2">
            @csrf

            @foreach ($allStates as $state)
                <label class="cursor-pointer">
                    <input type="checkbox" name="states[]" value="{{ $state }}" class="peer sr-only"
                           @checked($visibleStates === null || in_array($state, $visibleStates, true))>
                    <span class="pill opacity-45 transition peer-checked:border-accent/40 peer-checked:bg-accent/10 peer-checked:text-accent-text peer-checked:opacity-100">
                        {{ $state }}
                    </span>
                </label>
            @endforeach

            <button type="submit" class="btn btn-ghost text-[11px]">{{ __('app.ticket.states_save') }}</button>
            <span class="text-[10px] text-faint">{{ __('app.ticket.states_hint') }}</span>
        </form>
    </details>
@endif

<div class="flex flex-col gap-4 xl:flex-row xl:items-start">
    <div class="linear-board min-w-0 flex-1" data-state-board>
        @forelse ($stateColumns as $column)
            {{-- a column with no Linear name is the local one, and nothing can be dropped into it --}}
            <section class="linear-column" @if ($column['name'] !== null) data-state-column="{{ $column['name'] }}" @endif>
                <header class="linear-column-head">
                    <span class="flex min-w-0 items-center gap-2">
                        <span @class([
                                'size-3 shrink-0 rounded-full border-2',
                                'border-work bg-work' => $column['type'] === 'completed',
                                'border-accent' => $column['type'] === 'started',
                                'border-line-strong' => ! in_array($column['type'], ['completed', 'started'], true),
                             ])></span>
                        <span class="heading truncate">{{ $column['label'] }}</span>
                    </span>

                    <span class="metric shrink-0 text-[11px] text-faint">{{ $column['count'] }}</span>
                </header>

                <div class="linear-column-body" data-auto-animate>
                    @foreach ($column['groups'] as $group)
                        <details class="linear-group" open>
                            <summary class="linear-group-head">
                                <x-icon name="chevron-down" class="size-3 shrink-0 transition"/>
                                <span class="min-w-0 flex-1 truncate">{{ $group['project'] ?? __('app.ticket.no_project') }}</span>
                                <span class="metric shrink-0 text-[10px] text-faint">{{ count($group['tickets']) }}</span>
                            </summary>

                            <div class="mt-1.5 space-y-1.5">
                                @foreach ($group['tickets'] as $ticket)
                                    <x-ticket-card :ticket="$ticket" :focused="$focused" :states="$states"/>
                                @endforeach
                            </div>
                        </details>
                    @endforeach

                    @if ($column['groups'] === [])
                        <p class="rounded-[var(--radius-control)] border border-dashed border-line px-2 py-4 text-center text-[10px] text-faint">
                            {{ __('app.ticket.empty_column') }}
                        </p>
                    @endif
                </div>
            </section>
        @empty
            <x-card>
                <x-empty pose="search" :title="__('app.ticket.no_states')" :hint="__('app.ticket.no_states_hint')"/>
            </x-card>
        @endforelse
    </div>

    @if ($sprint !== null)
        <x-card class="w-full shrink-0 xl:w-72">
            <div class="flex items-center justify-between gap-2">
                <h2 class="flex min-w-0 items-center gap-2 text-sm font-semibold text-ink">
                    <x-icon name="repeat" class="size-3.5 shrink-0 text-dim"/>
                    <span class="truncate">{{ $sprint['cycle']['name'] ?: __('app.sprint.number', ['number' => $sprint['cycle']['number']]) }}</span>
                </h2>

                <span class="pill shrink-0 text-[10px]">{{ $sprint['from']->isoFormat('D. MMM') }} → {{ $sprint['to']->isoFormat('D. MMM') }}</span>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                @foreach ([
                    [__('app.sprint.scope'), $sprint['scope'], 'text-ink', 100],
                    [__('app.sprint.started'), $sprint['started'], 'text-accent-text', $sprint['scope'] > 0 ? round($sprint['started'] / $sprint['scope'] * 100) : 0],
                    [__('app.sprint.done'), $sprint['done'], 'text-work-text', $sprint['scope'] > 0 ? round($sprint['done'] / $sprint['scope'] * 100) : 0],
                ] as [$label, $value, $tone, $percent])
                    <div class="tile px-2 py-2">
                        <p class="metric text-lg font-bold {{ $tone }}">{{ $value }}</p>
                        <p class="truncate text-[10px] uppercase tracking-wide text-faint">{{ $label }}</p>
                        @if ($label !== __('app.sprint.scope'))
                            <p class="metric text-[10px] text-dim">{{ $percent }}%</p>
                        @endif
                    </div>
                @endforeach
            </div>

            {{--
                A burn-up, drawn from completion timestamps: every point is a ticket that actually
                closed that day. The scope is a flat line because the API gives it as it stands now
                and not as it stood on each day — a sloping one would be invention. The dashed line
                is the only construction here, and it is drawn as one.
            --}}
            @php
                $peak = max(1, $sprint['scope']);
                $points = collect($sprint['days']);
                $step = $points->count() > 1 ? 100 / ($points->count() - 1) : 100;
                $path = $points
                    ->reject(fn (array $day): bool => $day['future'])
                    ->map(fn (array $day, int $i): string => round($i * $step, 2).','.round(100 - $day['done'] / $peak * 100, 2))
                    ->implode(' ');
                $ideal = $points
                    ->map(fn (array $day, int $i): string => round($i * $step, 2).','.round(100 - $day['ideal'] / $peak * 100, 2))
                    ->implode(' ');
            @endphp

            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="mt-4 h-28 w-full" aria-hidden="true">
                <line x1="0" y1="0" x2="100" y2="0" stroke="var(--color-line-strong)" stroke-width="0.5" vector-effect="non-scaling-stroke"/>
                <polyline points="{{ $ideal }}" fill="none" stroke="var(--color-accent)" stroke-width="1"
                          stroke-dasharray="3 3" opacity=".5" vector-effect="non-scaling-stroke"/>
                @if ($path !== '')
                    <polyline points="{{ $path }}" fill="none" stroke="var(--color-work)" stroke-width="2"
                              stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
                @endif
            </svg>

            <div class="flex items-center justify-between text-[10px] text-faint">
                <span>{{ $sprint['from']->isoFormat('D. MMM') }}</span>
                <span class="metric">{{ $sprint['done'] }} / {{ $sprint['scope'] }}</span>
                <span>{{ $sprint['to']->isoFormat('D. MMM') }}</span>
            </div>

            <div class="mt-4 space-y-1.5 border-t border-line pt-3">
                <p class="heading">{{ __('app.ticket.prop_project') }}</p>

                @foreach ($sprint['projects'] as $project)
                    <div class="flex items-center gap-2">
                        <span class="min-w-0 flex-1 truncate text-xs text-ink">{{ $project['project'] ?? __('app.ticket.no_project') }}</span>
                        <span class="h-1 w-16 shrink-0 overflow-hidden rounded-[var(--radius-pill)] bg-raised">
                            <span class="block h-full rounded-[var(--radius-pill)] bg-work"
                                  style="inline-size: {{ $project['count'] > 0 ? round($project['done'] / $project['count'] * 100) : 0 }}%"></span>
                        </span>
                        <span class="metric shrink-0 text-[10px] text-faint">{{ $project['done'] }}/{{ $project['count'] }}</span>
                    </div>
                @endforeach
            </div>

            @if ($sprint['seconds'] > 0)
                <p class="mt-3 border-t border-line pt-3 text-[11px] text-faint">
                    {{ __('app.sprint.on_issues', ['duration' => Duration::human($sprint['seconds'])]) }}
                </p>
            @endif
        </x-card>
    @endif
</div>
