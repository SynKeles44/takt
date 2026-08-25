<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.entries.today_title') }}</h2>
        <span class="pill">{{ trans_choice('app.entries.count', $entries->count()) }}</span>
    </div>

    <div class="mt-4 space-y-2">
        @forelse ($entries as $entry)
            <x-entry-row :entry="$entry"/>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-8 text-center text-sm text-faint">
                {{ __('app.entries.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
