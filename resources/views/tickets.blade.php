@use('App\Support\Duration')

<x-app-layout :title="__('app.tickets.title')" :wide="true">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.tickets.title') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.ticket.inbox_hint') }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('tickets') }}" class="flex flex-wrap items-center gap-2">
                    <input type="hidden" name="tage" value="{{ $days }}">
                    <input type="hidden" name="ansicht" value="{{ $view }}">
                    <input type="search" name="q" value="{{ $term }}" class="control w-44 text-xs"
                           placeholder="{{ __('app.tickets.search') }}">
                </form>

                <div class="segmented">
                    @foreach (['board' => __('app.ticket.board'), 'liste' => __('app.ticket.list')] as $value => $label)
                        <a href="{{ route('tickets', ['ansicht' => $value, 'tage' => $days, 'q' => $term]) }}"
                           @class(['segment', 'segment-active' => $view === $value])>{{ $label }}</a>
                    @endforeach
                </div>

                <div class="segmented">
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

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-line pt-3">
            <span class="pill text-[10px]">{{ __('app.tickets.shown', ['shown' => $shown, 'total' => $total]) }}</span>

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

            @if ($stuck->isNotEmpty())
                <span class="pill border-danger/30 bg-danger/10 text-[10px] text-danger-text" title="{{ __('app.ticket.stuck_hint') }}">
                    {{ __('app.ticket.stuck') }}: {{ $stuck->count() }}
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

    <div data-region="ticket-board" class="mt-5">
        @if ($view === 'board')
            <div class="ticket-board" data-ticket-board>
                @foreach ($columns as $column)
                    @php $cards = $board[$column->value]; @endphp

                    <section class="ticket-column" data-column="{{ $column->value }}" style="--column-accent: {{ $column->accent() }}">
                        <header class="ticket-column-head">
                            <span class="flex items-center gap-1.5">
                                <x-icon :name="$column->icon()" class="size-3.5"/>
                                <span class="heading">{{ $column->label() }}</span>
                            </span>
                            <span class="metric text-[11px] text-faint">{{ $cards->count() }}</span>
                        </header>

                        <p class="px-1 pb-2 text-[10px] leading-snug text-faint">{{ $column->hint() }}</p>

                        <div class="ticket-column-body">
                            @forelse ($cards as $ticket)
                                <x-ticket-card :ticket="$ticket" :columns="$columns" :focused="$focused"/>
                            @empty
                                <p class="rounded-[var(--radius-control)] border border-dashed border-line px-2 py-4 text-center text-[10px] text-faint">
                                    {{ __('app.ticket.empty_column') }}
                                </p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            <x-card>
                <h2 class="heading">{{ __('app.ticket.list') }}</h2>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($board as $cards)
                        @foreach ($cards as $ticket)
                            <x-ticket-card :ticket="$ticket" :columns="$columns" :focused="$focused"/>
                        @endforeach
                    @endforeach

                    @foreach ($inbox as $ticket)
                        <x-ticket-card :ticket="$ticket" :columns="$columns" :focused="$focused"/>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>

    @if ($view === 'board' && $inbox->isNotEmpty())
        <x-card class="mt-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="heading">{{ __('app.ticket.inbox') }}</h2>
                    <p class="mt-0.5 text-[11px] text-faint">{{ __('app.ticket.inbox_hint') }}</p>
                </div>
                <span class="pill text-[10px]">{{ $inbox->count() }}</span>
            </div>

            <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($inbox as $ticket)
                    <x-ticket-card :ticket="$ticket" :columns="$columns" :focused="$focused"/>
                @endforeach
            </div>
        </x-card>
    @endif

    <x-card class="mt-5">
        <h2 class="heading">{{ __('app.ticket.new') }}</h2>
        <p class="mt-0.5 text-[11px] text-faint">{{ __('app.ticket.new_hint') }}</p>

        <form method="POST" action="{{ route('tickets.store') }}" class="mt-3 flex flex-wrap items-end gap-2">
            @csrf
            <label class="min-w-48 flex-1">
                <span class="label">{{ __('app.ticket.new_title') }}</span>
                <input type="text" name="titel" required maxlength="200" class="control mt-1 w-full text-sm">
            </label>

            <label>
                <span class="label">{{ __('app.ticket.column.none') }}</span>
                <select name="spalte" class="control mt-1 text-sm">
                    @foreach ($columns as $column)
                        <option value="{{ $column->value }}" @selected($column->value === 'next')>{{ $column->label() }}</option>
                    @endforeach
                </select>
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

                <ul class="mt-3 space-y-1.5">
                    @foreach ($loose as $ticket)
                        <li class="row flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <span class="metric shrink-0 text-xs text-dim">{{ $ticket['id'] }}</span>
                            <span class="line-clamp-1 min-w-0 flex-1 text-xs text-muted">{{ $ticket['title'] }}</span>

                            @foreach (array_slice($ticket['projects'], 0, 2) as $project)
                                <span class="pill shrink-0 text-[9px]">{{ $project }}</span>
                            @endforeach

                            <span class="metric shrink-0 text-[10px] text-faint">{{ $ticket['last']->isoFormat('D. MMM YY') }}</span>

                            <span class="flex shrink-0 items-center gap-1">
                                <form method="POST" action="{{ route('tickets.place') }}" data-live>
                                    @csrf
                                    <input type="hidden" name="key" value="{{ $ticket['id'] }}">
                                    <input type="hidden" name="spalte" value="next">
                                    <button type="submit" class="btn btn-ghost text-[10px]">{{ __('app.ticket.take_over') }}</button>
                                </form>

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
</x-app-layout>
