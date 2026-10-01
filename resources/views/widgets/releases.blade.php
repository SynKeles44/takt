<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.releases.label') }}</h2>
        <a href="{{ route('releases') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    <div class="mt-4 space-y-1.5">
        @forelse ($releases ?? [] as $row)
            <a href="{{ route('releases') }}" class="row flex items-center gap-3 px-3 py-2">
                <x-icon name="tag" class="size-3.5 shrink-0 text-dim"/>
                <span class="min-w-0 flex-1">
                    <span class="metric block truncate text-sm text-ink">{{ $row['release']['tag'] }}</span>
                    <span class="block truncate text-[11px] text-dim">{{ $row['project']->name }}</span>
                </span>
                <span class="shrink-0 text-[11px] text-faint">{{ $row['release']['at']->diffForHumans(short: true) }}</span>
            </a>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ $releases === null ? __('app.widget.releases.cold') : __('app.widget.releases.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
