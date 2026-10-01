<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.runs.label') }}</h2>
        <a href="{{ route('commands') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    <div class="mt-4 space-y-1.5">
        @forelse ($runs as $run)
            <a href="{{ route('commands.show', $run) }}" class="row flex items-center gap-3 px-3 py-2">
                <x-icon name="terminal" class="size-3.5 shrink-0 text-dim"/>
                <span class="min-w-0 flex-1">
                    <span class="metric block truncate text-sm text-ink">{{ $run->command() }}</span>
                    <span class="block truncate text-[11px] text-dim">
                        {{ $run->project?->name ?? '–' }} · {{ $run->started_at?->diffForHumans(short: true) }}
                    </span>
                </span>
                <span class="pill shrink-0 border text-[10px] {{ $run->status->classes() }}">{{ $run->status->label() }}</span>
            </a>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.widget.runs.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
