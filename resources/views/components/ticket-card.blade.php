@use('App\Support\Duration')
@props(['ticket', 'focused' => null, 'states' => []])

@php
    $local = $ticket['local'] ?? null;
    $isFocused = $focused !== null && $focused->key === $ticket['id'];
    $drafts = collect($ticket['pulls'] ?? [])->filter(fn (array $pull): bool => ($pull['draft'] ?? false) === true)->count();
    $ready = count($ticket['pulls'] ?? []) - $drafts;

    /*
     * Priority as four bars, the way Linear draws it — urgent and high are worth seeing from
     * across the board, and a word in a pill is not. Linear counts 1 as urgent down to 4 as low,
     * with 0 meaning nobody said.
     */
    $priority = $ticket['priority_value'] ?? null;
    $bars = $priority === null || $priority === 0 ? 0 : 5 - $priority;

    // the workflow of this ticket's own team, empty for a local ticket or an unknown team
    $workflow = $states[explode('-', $ticket['id'], 2)[0]] ?? [];
@endphp

<article @class(['ticket-card group', 'ticket-card-focus' => $isFocused]) data-ticket="{{ $ticket['id'] }}" draggable="true">
    <div class="flex items-center gap-2">
        <a href="{{ route('tickets.show', ['key' => $ticket['id']]) }}"
           class="metric shrink-0 text-[11px] text-dim hover:text-accent-text">{{ $ticket['id'] }}</a>

        @if ($bars > 0)
            <span class="flex shrink-0 items-end gap-[2px]" title="{{ __('app.ticket.priority') }}: {{ $ticket['priority'] }}">
                @for ($i = 1; $i <= 3; $i++)
                    <span @class([
                            'block w-[3px] rounded-[1px]',
                            'bg-danger' => $bars >= 4 && $i <= $bars - 1,
                            'bg-muted' => $bars < 4 && $i <= $bars - 1,
                            'bg-line-strong' => $i > $bars - 1,
                         ])
                         style="block-size: {{ 3 + $i * 2 }}px"></span>
                @endfor
            </span>
        @endif

        @if ($isFocused)
            <span class="pill shrink-0 border-work/40 bg-work/15 text-[9px] text-work-text">{{ __('app.ticket.focus_now') }}</span>
        @endif

        <span class="ms-auto flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100 focus-within:opacity-100">
            <form method="POST" action="{{ route('tickets.timer', ['key' => $ticket['id']]) }}" data-live>
                @csrf
                <button type="submit" class="icon-action size-5" title="{{ __('app.ticket.timer_start') }}">
                    <x-icon name="play" class="size-3"/>
                </button>
            </form>
        </span>
    </div>

    <a href="{{ route('tickets.show', ['key' => $ticket['id']]) }}" class="mt-1.5 flex items-start gap-2">
        {{-- the state as a ring, filled by how far along it is: the one glyph that says it at card size --}}
        <span @class([
                'mt-[3px] size-3 shrink-0 rounded-full border-2',
                'border-work bg-work' => ($ticket['state_type'] ?? null) === 'completed',
                'border-accent' => ($ticket['state_type'] ?? null) === 'started',
                'border-line-strong' => ! in_array($ticket['state_type'] ?? null, ['completed', 'started'], true),
             ])></span>

        <span class="line-clamp-2 text-sm leading-snug text-ink">{{ $ticket['title'] ?: __('app.ticket.not_found', ['id' => $ticket['id']]) }}</span>
    </a>

    <div class="mt-2 flex flex-wrap items-center gap-1">
        {{--
            Linear's state, on the card, next to a column that is mine. Linear's board puts the
            state in the column header and leaves it off the card, because there it is the same
            thing; here the columns describe my day and the state describes the team's workflow, so
            the two can disagree — and this pill is where that disagreement is visible.
        --}}
        @if (($ticket['state'] ?? null) !== null)
            @if ($workflow !== [])
                {{--
                    A select rather than a menu, and no form around it. Eighty-eight menus is a lot
                    of markup for something opened once; eighty-eight forms was measured at 300 KB
                    of CSRF fields the last time this page carried one per card. The browser builds
                    the options when the select is opened, and the write goes out from one
                    delegated listener with the token from the page's own meta tag.
                --}}
                <select data-state-select data-key="{{ $ticket['id'] }}"
                        @class([
                            'pill cursor-pointer appearance-none text-[9px]',
                            'border-work/30 bg-work/10 text-work-text' => $ticket['state_type'] === 'completed',
                            'border-accent/30 bg-accent/10 text-accent-text' => $ticket['state_type'] === 'started',
                        ])
                        title="{{ __('app.ticket.set_state') }}">
                    @foreach ($workflow as $name => $state)
                        <option value="{{ $name }}" @selected($name === $ticket['state'])>{{ $name }}</option>
                    @endforeach
                </select>
            @else
                <span @class([
                        'pill text-[9px]',
                        'border-work/30 bg-work/10 text-work-text' => $ticket['state_type'] === 'completed',
                        'border-accent/30 bg-accent/10 text-accent-text' => $ticket['state_type'] === 'started',
                    ])>{{ $ticket['state'] }}</span>
            @endif
        @endif

        @if (($ticket['points'] ?? null) !== null)
            <span class="pill text-[9px] text-dim" title="{{ __('app.ticket.points') }}">
                <x-icon name="chart" class="size-2.5"/>
                {{ rtrim(rtrim(number_format((float) $ticket['points'], 1, ',', ''), '0'), ',') }}
            </span>
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

        @foreach (array_slice($ticket['labels'] ?? [], 0, 2) as $label)
            <span class="pill text-[9px] text-dim">
                <span class="size-1.5 rounded-full" style="background: {{ $label['color'] ?? 'var(--color-accent)' }}"></span>
                {{ $label['name'] }}
            </span>
        @endforeach

        @if (($ticket['project'] ?? null) !== null)
            <span class="pill max-w-32 truncate text-[9px] text-dim" title="{{ $ticket['project'] }}">{{ $ticket['project'] }}</span>
        @endif

        @if ($ready > 0)
            <span class="pill border-accent/30 bg-accent/10 text-[9px] text-accent-text">{{ trans_choice('app.tickets.pulls', $ready) }}</span>
        @elseif ($drafts > 0)
            <span class="pill text-[9px] text-dim">{{ trans_choice('app.tickets.pulls', $drafts) }}</span>
        @endif

        @if (count($ticket['commits'] ?? []) > 0)
            <span class="pill text-[9px] text-faint">{{ trans_choice('app.tickets.commits', count($ticket['commits'])) }}</span>
        @endif

        @if (($ticket['state'] ?? null) === null && ($ticket['source'] ?? null) === 'local')
            <span class="pill text-[9px] text-dim">{{ __('app.ticket.local') }}</span>
        @endif
    </div>

    <p class="metric mt-2 text-[10px] text-faint">{{ __('app.ticket.updated', ['when' => $ticket['last']->diffForHumans(short: true)]) }}</p>
</article>
