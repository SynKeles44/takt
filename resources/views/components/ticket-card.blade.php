@use('App\Support\Duration')
@use('App\Enums\TicketColumn')

@props(['ticket', 'columns' => [], 'focused' => null])

@php
    $local = $ticket['local'] ?? null;
    $column = $ticket['column'] ?? null;
    $index = $column === null ? null : array_search($column, $columns, true);
    $previous = $index === null || $index === false || $index === 0 ? null : $columns[$index - 1];
    $next = $index === null || $index === false || $index >= count($columns) - 1 ? null : $columns[$index + 1];
    $days = $local?->daysInColumn();
    $isFocused = $focused !== null && $focused->key === $ticket['id'];
    $drafts = collect($ticket['pulls'] ?? [])->filter(fn (array $pull): bool => ($pull['draft'] ?? false) === true)->count();
    $ready = count($ticket['pulls'] ?? []) - $drafts;
@endphp

<article @class(['ticket-card', 'ticket-card-focus' => $isFocused]) data-ticket="{{ $ticket['id'] }}" draggable="true">
    <div class="flex items-start gap-2">
        <a href="{{ route('tickets.show', ['key' => $ticket['id']]) }}"
           class="metric shrink-0 text-xs font-semibold text-accent-text hover:underline">{{ $ticket['id'] }}</a>

        @if ($isFocused)
            <span class="pill shrink-0 border-work/40 bg-work/15 text-[9px] text-work-text">{{ __('app.ticket.focus_now') }}</span>
        @endif

        <span class="ms-auto flex shrink-0 items-center gap-1">
            @if ($previous !== null)
                <form method="POST" action="{{ route('tickets.place') }}" data-live>
                    @csrf
                    <input type="hidden" name="key" value="{{ $ticket['id'] }}">
                    <input type="hidden" name="spalte" value="{{ $previous->value }}">
                    <button type="submit" class="icon-action size-6" title="{{ __('app.ticket.move_to', ['column' => $previous->label()]) }}">
                        <x-icon name="chevron-left" class="size-3"/>
                    </button>
                </form>
            @endif

            @if ($next !== null)
                <form method="POST" action="{{ route('tickets.place') }}" data-live>
                    @csrf
                    <input type="hidden" name="key" value="{{ $ticket['id'] }}">
                    <input type="hidden" name="spalte" value="{{ $next->value }}">
                    <button type="submit" class="icon-action size-6" title="{{ __('app.ticket.move_to', ['column' => $next->label()]) }}">
                        <x-icon name="chevron-right" class="size-3"/>
                    </button>
                </form>
            @endif
        </span>
    </div>

    <a href="{{ route('tickets.show', ['key' => $ticket['id']]) }}" class="mt-1.5 block">
        <span class="line-clamp-2 text-sm leading-snug text-ink">{{ $ticket['title'] ?: __('app.ticket.not_found', ['id' => $ticket['id']]) }}</span>
    </a>

    <div class="mt-2 flex flex-wrap items-center gap-1">
        @if (($ticket['state'] ?? null) !== null)
            <span @class([
                    'pill text-[9px]',
                    'border-work/30 bg-work/10 text-work-text' => $ticket['state_type'] === 'completed',
                    'border-accent/30 bg-accent/10 text-accent-text' => $ticket['state_type'] === 'started',
                ])>{{ $ticket['state'] }}</span>
        @elseif (($ticket['source'] ?? null) === 'local')
            <span class="pill text-[9px] text-dim">{{ __('app.ticket.local') }}</span>
        @endif

        @if (($ticket['booked'] ?? 0) > 0)
            <span class="pill border-work/30 bg-work/10 text-[9px] text-work-text"
                  title="{{ __('app.ticket.booked_measured') }}">{{ Duration::human($ticket['booked']) }}</span>
        @elseif (($ticket['split'] ?? 0) > 0)
            <span class="pill text-[9px] text-dim" title="{{ __('app.ticket.booked_split') }}">~ {{ Duration::human($ticket['split']) }}</span>
        @endif

        @if (($ticket['estimate'] ?? null) !== null)
            <span class="pill text-[9px] text-faint">/ {{ Duration::human($ticket['estimate']) }}</span>
        @endif

        @if ($ready > 0)
            <span class="pill border-accent/30 bg-accent/10 text-[9px] text-accent-text">{{ trans_choice('app.tickets.pulls', $ready) }}</span>
        @elseif ($drafts > 0)
            <span class="pill text-[9px] text-dim">{{ trans_choice('app.tickets.pulls', $drafts) }}</span>
        @endif

        @if (count($ticket['commits'] ?? []) > 0)
            <span class="pill text-[9px] text-faint">{{ trans_choice('app.tickets.commits', count($ticket['commits'])) }}</span>
        @endif
    </div>

    @if ($column === TicketColumn::Waiting && filled($local?->waiting_reason))
        <p class="mt-2 line-clamp-1 text-[10px] text-rest-text">{{ $local->waiting_reason }}</p>
    @endif

    <div class="mt-2 flex items-center justify-between gap-2">
        @if ($days !== null && $days > 0)
            <span @class(['metric text-[10px]', 'text-danger-text' => $days >= 5, 'text-faint' => $days < 5])>
                {{ __('app.ticket.waiting_since', ['days' => $days]) }}
            </span>
        @else
            <span class="metric text-[10px] text-faint">{{ $ticket['last']->isoFormat('D. MMM') }}</span>
        @endif

        <form method="POST" action="{{ route('tickets.timer', ['key' => $ticket['id']]) }}" data-live>
            @csrf
            <button type="submit" class="icon-action size-6" title="{{ __('app.ticket.timer_start') }}">
                <x-icon name="play" class="size-3"/>
            </button>
        </form>
    </div>
</article>
