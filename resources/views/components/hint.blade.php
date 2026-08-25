@props(['title' => null, 'align' => 'end'])

{{-- A small (i) that shows its help on hover, and on keyboard focus just the same. --}}
<span class="hint">
    <button type="button" class="hint-toggle" aria-label="{{ $title ?? __('app.hint.label') }}">
        <x-icon name="info" class="size-3.5"/>
    </button>

    <span @class(['hint-panel', 'hint-panel-start' => $align === 'start']) role="tooltip">
        @if ($title)
            <span class="mb-1.5 block text-xs font-semibold text-ink">{{ $title }}</span>
        @endif

        {{ $slot }}
    </span>
</span>
