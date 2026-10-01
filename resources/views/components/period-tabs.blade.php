@props(['active', 'anchor' => null])

@php
    $tabs = [
        'woche' => __('app.insights.week'),
        'monat' => __('app.insights.month'),
        'jahr' => __('app.insights.year'),
    ];
@endphp

<div class="tile flex items-center gap-1 p-1" data-period-row>
    @foreach ($tabs as $period => $label)
        <a href="{{ route('insights', array_filter(['zeitraum' => $period, 'stand' => $anchor])) }}"
           @class([
               'relative rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition',
               'text-ink' => $active === $period,
               'text-muted hover:text-ink' => $active !== $period,
           ])>
            @if ($active === $period)
                <span class="tab-marker" aria-hidden="true"></span>
            @endif

            <span class="relative">{{ $label }}</span>
        </a>
    @endforeach
</div>
