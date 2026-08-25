<x-card>
    <h2 class="heading">{{ __('app.widget.todo_tags.label') }}</h2>

    <div class="mt-4 flex flex-wrap gap-2">
        @forelse ($tags as $tag)
            <span class="pill {{ $tag['classes'] }}">
                {{ $tag['label'] }}
                <span class="metric ml-1">{{ $tag['count'] }}</span>
            </span>
        @empty
            <p class="w-full rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.widget.todo_tags.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
