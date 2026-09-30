@props([
    'type' => 'row',
    'count' => 3,
    'heading' => false,
])

{{--
    The shape of what is coming. A skeleton earns its place only by matching the geometry of the
    real content — same row height, same rhythm — otherwise it is a spinner with extra steps and
    the layout still jumps when the data lands.
--}}
<div {{ $attributes->class('space-y-2') }} aria-hidden="true">
    @if ($heading)
        <div class="skeleton skeleton-line w-32"></div>
    @endif

    @foreach (range(1, max(1, (int) $count)) as $index)
        <div @class([
            'skeleton',
            'skeleton-row' => $type === 'row',
            'skeleton-card' => $type === 'card',
            'skeleton-line' => $type === 'line',
        ]) style="opacity: {{ number_format(1 - ($index - 1) * 0.18, 2, '.', '') }}"></div>
    @endforeach
</div>
