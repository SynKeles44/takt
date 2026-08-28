<x-card>
    <div class="flex items-start gap-3">
        <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="plus" class="size-4"/></span>
        <div>
            <h2 class="text-sm font-semibold text-ink">{{ __('app.entries.manual_title') }}</h2>
            <p class="text-xs text-faint">{{ __('app.entries.manual_hint') }}</p>
        </div>
    </div>

    <div class="mt-5">
        <x-booking-form :open-todos="$openTodos" :pattern="$pattern"/>
    </div>
</x-card>
