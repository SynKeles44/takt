<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.docker.label') }}</h2>
        <a href="{{ route('docker') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    @if (! $docker['ok'])
        <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
            {{ $docker['error'] }}
        </p>
    @else
        <p class="metric mt-4 text-3xl font-bold {{ $docker['running'] > 0 ? 'text-work-text' : 'text-dim' }}">
            {{ $docker['running'] }}<span class="text-lg text-faint">/{{ $docker['total'] }}</span>
        </p>
        <p class="text-[11px] text-faint">{{ __('app.widget.docker.running') }}</p>

        <div class="mt-4 space-y-1.5">
            @forelse ($docker['groups'] as $group)
                <div class="row flex items-center gap-3 px-3 py-2">
                    <span class="size-2 shrink-0 rounded-full {{ $group['running'] > 0 ? 'bg-work' : 'bg-line' }}"></span>
                    <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $group['label'] }}</span>
                    <span class="metric shrink-0 text-[11px] text-dim">{{ $group['running'] }}/{{ $group['total'] }}</span>
                </div>
            @empty
                <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                    {{ __('app.widget.docker.empty') }}
                </p>
            @endforelse
        </div>
    @endif
</x-card>
