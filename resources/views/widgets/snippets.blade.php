<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.dev.snippets') }}</h2>
        <a href="{{ route('snippets') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    <div class="mt-4 space-y-1.5">
        @forelse ($snippets as $snippet)
            <button type="button" class="row flex w-full items-center gap-3 px-3 py-2 text-left"
                    data-copy="{{ $snippet->body }}" data-copy-ping="{{ route('snippets.used', $snippet) }}"
                    data-copy-label="{{ __('app.dev.copied') }}" title="{{ __('app.dev.copy') }}">
                <x-icon name="paperclip" class="size-3.5 shrink-0 text-dim"/>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-ink">{{ $snippet->title }}</span>
                    <span class="metric block truncate text-[11px] text-dim">{{ $snippet->body }}</span>
                </span>
                @if ($snippet->label)<span class="pill shrink-0 text-[10px]">{{ $snippet->label }}</span>@endif
            </button>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.dev.no_snippets') }}
            </p>
        @endforelse
    </div>
</x-card>
