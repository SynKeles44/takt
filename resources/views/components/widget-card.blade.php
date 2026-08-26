@props(['widget', 'pooled' => false])

{{-- pooled cards are the stock the gallery draws from; only a card on offer carries data-add-widget --}}
<button type="button" class="gallery-card"
        @if ($pooled) data-pool-widget="{{ $widget->value }}" @else data-add-widget="{{ $widget->value }}" @endif
        data-span="{{ $widget->span() }}" data-rows="{{ $widget->rows() }}"
        data-group="{{ $widget->group()->value }}" data-label="{{ $widget->label() }}"
        data-search="{{ mb_strtolower($widget->label().' '.$widget->description()) }}">
    <span class="gallery-frame" style="--frame-ratio: {{ round((163 * $widget->span() + 20 * ($widget->span() - 1)) / (80 * $widget->rows() + 20 * ($widget->rows() - 1)), 3) }}">
        <x-widget-shape :shape="$widget->shape()" :rows="$widget->rows()" :span="$widget->span()"/>
        <span class="gallery-size metric">{{ $widget->span() }}×{{ $widget->rows() }}</span>
        <span class="gallery-add"><x-icon name="plus" class="size-3.5"/></span>
    </span>

    <span class="mt-1.5 block min-w-0">
        <span class="block truncate text-xs font-semibold text-ink">{{ $widget->label() }}</span>
        <span class="mt-0.5 block text-[11px] leading-snug text-dim">{{ $widget->description() }}</span>
    </span>
</button>
