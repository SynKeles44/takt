@props(['theme'])

@php
    /*
     * Automatic follows the system, so it shows both halves — as one diagonal split of the
     * very same mock-up. Same content in every slide means the element never changes height.
     */
    $layers = $theme->isAutomatic()
        ? [[\App\Enums\Theme::Midnight, false], [\App\Enums\Theme::Daylight, true]]
        : [[$theme, false]];
@endphp

<span class="relative block overflow-hidden rounded-[var(--radius-control)] border border-line">
    @foreach ($layers as [$shown, $overlay])
        <span data-theme="{{ $shown->value }}"
              @class(['block bg-canvas p-3', 'absolute inset-0' => $overlay])
              @style(['clip-path: polygon(100% 0, 100% 100%, 0 100%)' => $overlay])>
            <span class="surface-plain block p-2.5">
                <span class="flex items-center gap-2">
                    <span class="size-1.5 shrink-0 rounded-full bg-accent"></span>
                    <span class="h-1 w-10 rounded-[var(--radius-pill)] bg-ink/70"></span>
                    <span class="pill ml-auto px-1.5 py-0.5 text-[9px]">{{ $shown->label() }}</span>
                </span>

                <span class="metric mt-2 block text-base font-bold text-work-text">7h 24m</span>

                <span class="row mt-2 flex items-center gap-2 px-2 py-1.5">
                    <span class="dot size-2 shrink-0 bg-work"></span>
                    <span class="h-1.5 w-14 rounded-[var(--radius-pill)] bg-line-strong"></span>
                    <span class="h-1.5 w-6 rounded-[var(--radius-pill)] bg-rest"></span>
                </span>

                <span class="mt-2 flex items-end gap-1">
                    <span class="h-3 w-2 rounded-sm bg-accent"></span>
                    <span class="h-5 w-2 rounded-sm bg-accent/60"></span>
                    <span class="h-4 w-2 rounded-sm bg-work"></span>
                    <span class="h-6 w-2 rounded-sm bg-accent"></span>
                    <span class="h-2 w-2 rounded-sm bg-rest"></span>
                    <span class="btn btn-primary ml-auto px-2 py-0.5 text-[9px]">{{ __('app.settings.style_preview_button') }}</span>
                </span>
            </span>
        </span>
    @endforeach
</span>
