<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.tickets.label') }}</h2>
        <a href="{{ route('tickets') }}" class="pill hover:text-ink">{{ __('app.widget.tickets.board') }}</a>
    </div>

    @if ($focused)
        <a href="{{ route('tickets.show', $focused->key) }}"
           class="row mt-4 flex items-center gap-3 border-accent/40 bg-accent/5 px-3 py-2.5">
            <x-icon name="play" class="size-3.5 shrink-0 text-accent-text"/>
            <span class="min-w-0 flex-1">
                <span class="metric block text-[10px] uppercase tracking-wide text-accent-text">{{ __('app.widget.tickets.focus') }}</span>
                <span class="block truncate text-sm text-ink">{{ $focused->title ?: $focused->key }}</span>
            </span>
            <span class="metric shrink-0 text-[11px] text-dim">{{ $focused->key }}</span>
        </a>
    @endif

    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        @foreach ([
            [__('app.ticket.open'), $open, 'text-ink'],
            [__('app.ticket.in_progress'), $started, 'text-accent-text'],
            [__('app.sprint.tab'), $sprint, 'text-work-text'],
        ] as [$label, $value, $tone])
            <div class="row px-2 py-2.5">
                <p class="metric text-xl font-bold {{ $tone }}">{{ $value }}</p>
                <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ $label }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 space-y-1.5">
        @forelse ($next as $issue)
            <a href="{{ route('tickets.show', $issue['id']) }}" class="row flex items-center gap-3 px-3 py-2">
                <span class="metric shrink-0 text-[11px] text-dim">{{ $issue['id'] }}</span>
                <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $issue['title'] ?: '–' }}</span>
                <span class="pill shrink-0 text-[10px]">{{ $issue['state'] }}</span>
            </a>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.widget.tickets.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
