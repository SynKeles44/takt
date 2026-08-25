@props(['style'])

<span data-style="{{ $style->value }}" class="pointer-events-none block">
    <span class="surface-plain block p-3">
        <span class="heading block">{{ __('app.settings.style_preview_label') }}</span>

        <span class="metric mt-1.5 block text-lg font-bold text-work-text">3h 02m</span>

        <span class="row mt-2.5 flex items-center gap-2">
            <span class="dot size-2 shrink-0 bg-work"></span>
            <span class="h-1.5 w-12 rounded-[var(--radius-pill)] bg-line-strong"></span>
            <span class="pill ml-auto px-1.5 py-0.5 text-[9px]">Tag</span>
        </span>

        <span class="btn btn-primary mt-2.5 w-full px-2 py-1 text-[10px]">{{ __('app.settings.style_preview_button') }}</span>
    </span>
</span>
