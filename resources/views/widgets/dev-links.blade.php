<x-card>
    <h2 class="heading">{{ __('app.widget.dev_links.label') }}</h2>

    <div class="mt-4 space-y-1.5">
        @forelse ($projects as $project)
            <a href="https://github.com/{{ $project->repository }}" target="_blank" class="row flex items-center gap-3 px-3 py-2">
                <x-icon name="terminal" class="size-3.5 shrink-0 text-dim"/>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-ink">{{ $project->name }}</span>
                    <span class="metric block truncate text-[11px] text-dim">{{ $project->repository }}</span>
                </span>
                <x-icon name="external" class="size-3.5 shrink-0 text-dim"/>
            </a>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.widget.dev_links.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
