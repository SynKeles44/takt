@use('App\Support\Duration')

<x-app-layout :title="__('app.tickets.title')" :wide="true">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.tickets.title') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.tickets.intro') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('tickets') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tage" value="{{ $days }}">
                    <input type="search" name="q" value="{{ $term }}" class="control w-48 text-xs"
                           placeholder="{{ __('app.tickets.search') }}">

                    <div class="segmented">
                        @foreach (['offen' => __('app.tickets.open'), 'alle' => __('app.tickets.all')] as $value => $label)
                            <button type="submit" name="status" value="{{ $value }}"
                                    @class(['segment', 'segment-active' => $status === $value])>{{ $label }}</button>
                        @endforeach
                    </div>
                </form>

                <form method="POST" action="{{ route('tickets.refresh') }}" data-live>
                    @csrf
                    <button type="submit" class="icon-action" aria-label="{{ __('app.tickets.refresh') }}" title="{{ __('app.tickets.refresh') }}">
                        <x-icon name="repeat" class="size-4"/>
                    </button>
                </form>
            </div>
        </div>
    </x-card>

    @if (! $configured)
        <x-card class="mt-5">
            <p class="text-sm text-dim">{{ __('app.tickets.no_token') }}</p>
            <a href="{{ route('settings') }}" class="btn btn-ghost mt-3 text-xs">
                <x-icon name="gear" class="size-3.5"/>
                {{ __('app.linear.token') }}
            </a>
        </x-card>
    @elseif ($error !== null)
        <x-card class="mt-5">
            <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                {{ $error }}
            </p>
        </x-card>
    @endif

    <x-card class="mt-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="heading">{{ __('app.tickets.window', ['days' => $days]) }}</h2>

            <div class="flex items-center gap-2">
                <div class="segmented">
                    @foreach ($windows as $window)
                        <a href="{{ route('tickets', ['tage' => $window, 'status' => $status, 'q' => $term]) }}"
                           @class(['segment', 'segment-active' => $window === $days])>{{ $window }}</a>
                    @endforeach
                </div>

                <span class="pill">{{ __('app.tickets.shown', ['shown' => $tickets->count(), 'total' => $total]) }}</span>
            </div>
        </div>

        @if ($tickets->isEmpty())
            <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-6 text-center text-xs text-faint">
                {{ __('app.tickets.empty', ['days' => $days]) }}
            </p>
        @else
            <div class="mt-4 space-y-2">
                @foreach ($tickets as $ticket)
                    <details class="tile overflow-hidden">
                        <summary class="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <x-icon name="chevron-right" class="size-3.5 shrink-0 text-dim transition"/>

                            <span class="metric shrink-0 text-sm font-semibold text-accent-text">{{ $ticket['id'] }}</span>

                            <span class="line-clamp-1 min-w-0 flex-1 text-sm text-ink">{{ $ticket['title'] }}</span>

                            @if ($ticket['state'] !== null)
                                <span @class([
                                        'pill shrink-0 text-[10px]',
                                        'border-work/30 bg-work/10 text-work-text' => $ticket['state_type'] === 'completed',
                                        'border-accent/30 bg-accent/10 text-accent-text' => $ticket['state_type'] === 'started',
                                    ])>{{ $ticket['state'] }}</span>
                            @else
                                <span class="pill shrink-0 text-[10px] text-dim">{{ __('app.tickets.only_git') }}</span>
                            @endif

                            @foreach (array_slice($ticket['projects'], 0, 2) as $project)
                                <span class="pill shrink-0 text-[10px]">{{ $project }}</span>
                            @endforeach

                            @if ($ticket['seconds'] > 0)
                                <span class="pill shrink-0 border-work/30 bg-work/10 text-[10px] text-work-text"
                                      title="{{ __('app.tickets.estimate_hint') }}">~ {{ Duration::human($ticket['seconds']) }}</span>
                            @endif

                            <span class="metric shrink-0 text-[11px] text-dim">{{ $ticket['last']->isoFormat('D. MMM') }}</span>
                        </summary>

                        <div class="space-y-3 border-t border-line p-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($ticket['url'] !== null)
                                    <a href="{{ $ticket['url'] }}" target="_blank" class="btn btn-ghost text-xs">
                                        <x-icon name="external" class="size-3.5"/>
                                        {{ __('app.linear.title') }}
                                    </a>
                                @endif

                                @foreach ([$ticket['team'], $ticket['assignee'], $ticket['priority']] as $fact)
                                    @if ($fact !== null)
                                        <span class="pill text-[10px]">{{ $fact }}</span>
                                    @endif
                                @endforeach
                            </div>

                            <p class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-faint">
                                <span>{{ trans_choice('app.tickets.commits', count($ticket['commits'])) }}</span>
                                <span>{{ trans_choice('app.tickets.pulls', count($ticket['pulls'])) }}</span>
                                <span>{{ trans_choice('app.tickets.branches', count($ticket['branches'])) }}</span>
                            </p>

                            @if ($ticket['pulls'] !== [])
                                <x-pull-list :pulls="$ticket['pulls']" :compact="true"/>
                            @endif

                            @if ($ticket['commits'] !== [])
                                <ul class="space-y-1.5">
                                    @foreach (array_slice($ticket['commits'], 0, 12) as $commit)
                                        <li class="row flex items-start gap-3 px-3 py-2">
                                            <span class="metric shrink-0 text-xs text-accent-text">{{ $commit['short'] }}</span>
                                            <span class="min-w-0 flex-1 text-sm text-ink">{{ $commit['subject'] }}</span>
                                            <span class="metric shrink-0 text-[11px] text-dim">{{ $commit['at']->isoFormat('D. MMM, HH:mm') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if ($ticket['branches'] !== [])
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach (array_slice($ticket['branches'], 0, 8) as $branch)
                                        <span class="pill metric text-[10px]">{{ $branch['name'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>

            <p class="mt-4 border-t border-line pt-3 text-[11px] leading-snug text-faint">
                {{ __('app.tickets.estimate_hint') }}
            </p>
        @endif
    </x-card>
</x-app-layout>
