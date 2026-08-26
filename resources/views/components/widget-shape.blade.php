@props(['shape', 'rows' => 3, 'span' => 2])

@php
    $lines = max(2, min(6, (int) $rows + 1));
    // a flat tile puts its metrics in one row; stacking them there only squeezes them
    $tileColumns = (int) $rows <= 2 && (int) $span >= 4 ? 4 : 2;
@endphp

<span class="shape shape-{{ $shape }}" aria-hidden="true">
    <span class="shape-head"></span>

    @switch($shape)
        @case('timer')
            <span class="shape-clock"></span>
            <span class="shape-row">
                <span class="shape-btn shape-btn-accent"></span>
                <span class="shape-btn"></span>
            </span>
            @break

        @case('metrics')
            <span class="shape-tiles" style="--tiles: {{ $tileColumns }}">
                @for ($i = 0; $i < 4; $i++)
                    <span class="shape-tile"><span class="shape-tile-value"></span></span>
                @endfor
            </span>
            @break

        @case('chart')
            <span class="shape-bars">
                @foreach ([55, 80, 40, 95, 65, 30, 70] as $height)
                    <span class="shape-bar" style="--bar: {{ $height }}%"></span>
                @endforeach
            </span>
            @break

        @case('heatmap')
            <span class="shape-grid">
                @for ($i = 0; $i < 84; $i++)
                    <span class="shape-cell" style="--cell: {{ [0, 0, 1, 2, 3, 2, 1, 0, 2, 3, 1, 2][$i % 12] }}"></span>
                @endfor
            </span>
            @break

        @case('list')
            <span class="shape-lines">
                @for ($i = 0; $i < $lines; $i++)
                    <span class="shape-line"><span class="shape-dot"></span><span class="shape-fill" style="--w: {{ [92, 74, 84, 62, 88, 70][$i % 6] }}%"></span></span>
                @endfor
            </span>
            @break

        @case('form')
            <span class="shape-form">
                <span class="shape-field"></span>
                <span class="shape-field"></span>
                <span class="shape-field shape-field-short"></span>
                <span class="shape-btn shape-btn-accent"></span>
            </span>
            @break

        @case('text')
            <span class="shape-text">
                <span class="shape-fill" style="--w: 96%"></span>
                <span class="shape-fill" style="--w: 88%"></span>
                <span class="shape-fill" style="--w: 64%"></span>
            </span>
            @break

        @case('pills')
            <span class="shape-pills">
                @foreach ([44, 32, 52, 28, 38] as $width)
                    <span class="shape-pill" style="--w: {{ $width }}px"></span>
                @endforeach
            </span>
            @break

        @case('buttons')
            <span class="shape-buttons">
                @for ($i = 0; $i < 3; $i++)
                    <span class="shape-block"></span>
                @endfor
            </span>
            @break
    @endswitch
</span>
