@props(['active', 'anchor' => null])

@php
    $tabs = [
        'woche' => __('app.insights.week'),
        'monat' => __('app.insights.month'),
        'jahr' => __('app.insights.year'),
    ];
@endphp

<div class="tile flex items-center gap-1 p-1">
    @foreach ($tabs as $period => $label)
        <a href="{{ route('insights', array_filter(['zeitraum' => $period, 'stand' => $anchor])) }}"
           @class([
               'rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition',
               'bg-hover text-ink' => $active === $period,
               'text-muted hover:text-ink' => $active !== $period,
           ])>
            {{ $label }}
        </a>
    @endforeach
</div>
