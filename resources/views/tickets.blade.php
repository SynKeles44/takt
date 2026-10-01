@use('App\Support\Duration')

{{-- only the board earns the full window: it scrolls sideways. The list and the sprints read like every other page. --}}
<x-app-layout :title="__('app.tickets.title')" :wide="$view === 'board' ? 'full' : true" :defer="$defer">
    {{--
        The header is the same width on all three views. Only the board's columns take the window,
        because nine of them have nothing to do with a reading width — everything above them does,
        and a header that changes size when the tab changes is the thing this avoids.
    --}}
    <x-card class="rise page-width">

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.tickets.title') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.ticket.board_hint') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('tickets') }}" class="flex flex-wrap items-center gap-2" data-live>
                <input type="hidden" name="tage" value="{{ $days }}">
                <input type="hidden" name="ansicht" value="{{ $view }}">
                <input type="search" name="q" value="{{ $term }}" class="control w-44 text-xs"
                       placeholder="{{ __('app.tickets.search') }}">
            </form>

                <div class="segmented" data-marker-key="tickets-view">
                    @foreach ([
                        'board' => __('app.ticket.board'),
                        'liste' => __('app.ticket.list'),
                        'sprints' => __('app.sprint.tab'),
                    ] as $value => $label)
                        <a href="{{ route('tickets', ['ansicht' => $value, 'tage' => $days, 'q' => $term]) }}"
                           @class(['segment', 'segment-active' => $view === $value])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="segmented" data-marker-key="tickets-window">
                    @foreach ($windows as $window)
                        <a href="{{ route('tickets', ['tage' => $window, 'ansicht' => $view, 'q' => $term]) }}"
                           @class(['segment', 'segment-active' => $window === $days])>{{ $window }}</a>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('tickets.refresh') }}" data-live>
                    @csrf
                    <button type="submit" class="icon-action" aria-label="{{ __('app.tickets.refresh') }}" title="{{ __('app.tickets.refresh') }}">
                        <x-icon name="repeat" class="size-4"/>
                    </button>
                </form>
            </div>
        </div>

        {{--
            The filters, from the tickets rather than from Linear: with eighty-eight assigned
            tickets this is what makes the board usable, and offering every project and label of
            every team would offer values that match nothing here. Each one submits on change —
            a filter you have to confirm is a filter nobody uses twice.

            On every view, not only where it was first needed: a row that appears and disappears
            moves every button above it each time the tab changes, and the sprint view filters by
            project and label as usefully as the board does.
        --}}
        <form method="GET" action="{{ route('tickets') }}"
              class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3" data-filter-form data-live>
            <input type="hidden" name="ansicht" value="{{ $view }}">
            <input type="hidden" name="tage" value="{{ $days }}">
            <input type="hidden" name="q" value="{{ $term }}">

            {{--
                The placeholders wear the real element's own classes rather than a height that
                looks about right. A hand-picked `h-8` was 11px short of what `.control` actually
                measures, and three of them plus the counter made the header jump 15px every time
                the content landed — which is the whole thing a skeleton exists to prevent.
            --}}
            @if ($defer)
                @foreach (range(1, 3) as $placeholder)
                    <span class="control skeleton w-auto min-w-28 text-xs"></span>
                @endforeach
            @endif

            @foreach ([
                ['sprint', __('app.sprint.tab')],
                ['projekt', __('app.ticket.prop_project')],
                ['label', __('app.ticket.labels')],
            ] as [$name, $label])
                @if (! $defer && $facets[$name] !== [])
                    <select name="{{ $name }}" class="control w-auto min-w-28 text-xs" onchange="this.form.requestSubmit()">
                        <option value="">{{ $label }}: {{ __('app.tickets.filter_all') }}</option>
                        @foreach ($facets[$name] as $value)
                            <option value="{{ $value }}" @selected($picked[$name] === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                @endif
            @endforeach

            @if (array_filter($picked) !== [])
                <a href="{{ route('tickets', ['ansicht' => $view, 'tage' => $days, 'q' => $term]) }}"
                   class="pill hover:text-ink">{{ __('app.tickets.filter_clear') }}</a>
            @endif
        </form>

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
            @if ($defer)
                <span class="pill skeleton w-20 text-[10px]">&nbsp;</span>
            @else
                <span class="pill text-[10px]">{{ __('app.tickets.shown', ['shown' => $shown, 'total' => $total]) }}</span>
            @endif

            @if ($focused !== null)
                <a href="{{ route('tickets.show', ['key' => $focused->key]) }}"
                   class="pill border-work/40 bg-work/15 text-[10px] text-work-text">
                    {{ __('app.ticket.focus_now') }}: {{ $focused->key }}
                </a>
            @endif

            @if ($calibration !== null)
                <span class="pill text-[10px]" title="{{ __('app.ticket.calibration_hint') }}">
                    {{ __('app.ticket.calibration_value', ['factor' => number_format($calibration['factor'], 2, ',', '.'), 'count' => $calibration['count']]) }}
                </span>
            @endif

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
            <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">{{ $error }}</p>
        </x-card>
    @endif

    <div data-region="ticket-board" @class(['mt-5', 'page-width' => $view !== 'board'])>
        @if ($defer)
            {{--
                The shape of the board, while Linear is still being asked. Column-shaped on the
                board and card-shaped on the list, because a skeleton that does not have the
                geometry of what replaces it is a spinner that also makes the page jump.
            --}}
            @if ($view === 'board')
                <div class="flex gap-3 overflow-hidden">
                    @foreach (range(1, 6) as $column)
                        <div class="w-72 shrink-0 space-y-2" style="opacity: {{ number_format(1 - ($column - 1) * 0.14, 2, '.', '') }}">
                            <div class="skeleton skeleton-line w-24"></div>
                            <x-skeleton type="card" :count="3"/>
                        </div>
                    @endforeach
                </div>
            @else
                <x-card>
                    <div class="skeleton skeleton-line w-24"></div>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach (range(1, 9) as $card)
                            <div class="skeleton skeleton-card"></div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        @elseif ($view === 'sprints')
            @include('partials.sprints')
        @elseif ($view === 'board')
            <p class="page-width mb-2 text-[10px] text-faint">{{ __('app.ticket.shortcuts_state') }}</p>

            @include('partials.linear-board')
        @else
            <x-card>
                <h2 class="heading">{{ __('app.ticket.list') }}</h2>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($rows as $ticket)
                        <x-ticket-card :ticket="$ticket" :focused="$focused" :states="$states"/>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>

    <x-card class="mt-5">
        <h2 class="heading">{{ __('app.ticket.new') }}</h2>
        <p class="mt-0.5 text-[11px] text-faint">{{ __('app.ticket.new_hint') }}</p>

        <form method="POST" action="{{ route('tickets.store') }}" class="mt-3 flex flex-wrap items-end gap-2" data-live>
            @csrf
            <label class="min-w-48 flex-1">
                <span class="label">{{ __('app.ticket.new_title') }}</span>
                <input type="text" name="titel" required maxlength="200" class="control mt-1 w-full text-sm">
            </label>

            <button type="submit" class="btn btn-primary text-xs">
                <x-icon name="plus" class="size-3.5"/>
                {{ __('app.ticket.create') }}
            </button>
        </form>
    </x-card>

    @if ($looseTotal > 0 || $ignored > 0)
        <x-card class="mt-5">
            <details>
                <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                    <x-icon name="chevron-right" class="size-3.5 text-dim"/>
                    <span class="heading">{{ __('app.ticket.loose') }}</span>
                    <span class="pill text-[10px]">{{ trans_choice('app.ticket.loose_count', $looseTotal) }}</span>

                    @if ($ignored > 0)
                        <span class="pill text-[10px] text-faint">{{ __('app.ticket.ignored_count', ['count' => $ignored]) }}</span>
                    @endif
                </summary>

                <p class="mt-2 text-[11px] leading-snug text-faint">{{ __('app.ticket.loose_hint') }}</p>

                <ul class="mt-3 space-y-1.5" data-auto-animate>
                    @foreach ($loose as $ticket)
                        <li class="row flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <span class="metric shrink-0 text-xs text-dim">{{ $ticket['id'] }}</span>
                            <span class="line-clamp-1 min-w-0 flex-1 text-xs text-muted">{{ $ticket['title'] }}</span>

                            @foreach (array_slice($ticket['projects'], 0, 2) as $project)
                                <span class="pill shrink-0 text-[9px]">{{ $project }}</span>
                            @endforeach

                            <span class="metric shrink-0 text-[10px] text-faint">{{ $ticket['last']->isoFormat('D. MMM YY') }}</span>

                            <span class="flex shrink-0 items-center gap-1">
                                <form method="POST" action="{{ route('tickets.loose') }}" data-live>
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $ticket['id'] }}">
                                    <input type="hidden" name="aktion" value="ignorieren">
                                    <button type="submit" class="icon-action size-6" title="{{ __('app.ticket.ignore') }}">
                                        <x-icon name="close" class="size-3"/>
                                    </button>
                                </form>
                            </span>
                        </li>
                    @endforeach
                </ul>

                @if ($looseTotal > $loose->count())
                    <p class="mt-2 text-[10px] text-faint">
                        {{ __('app.ticket.loose_more', ['count' => $looseTotal - $loose->count()]) }}
                    </p>
                @endif
            </details>
        </x-card>
    @endif

    <p class="mt-4 text-[11px] leading-snug text-faint">{{ __('app.tickets.estimate_hint') }}</p>
    <x-mascot pose="ticket" class="mascot-at-tail size-20"/>
</x-app-layout>
