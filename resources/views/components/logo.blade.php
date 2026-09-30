@props(['class' => 'size-10'])

@php
    // unique per instance: two marks on one page sharing a gradient id would blank one of them
    $id = 'mark-'.substr(md5($class.random_int(0, PHP_INT_MAX)), 0, 6);
@endphp

{{--
    The mark: Takti in white on the brand gradient, inside the rounded square every platform
    expects an icon to fill.

    The previous version put the violet figure on nothing at all, and it read as empty — correctly,
    because most of the frame WAS empty. A badge is not decoration here: it is what gives an icon
    its silhouette at 16 pixels and in a dock, and it is why Discord and Duolingo both put a light
    character on a filled colour rather than the other way round.

    The figure is --color-accent-ink and NOT white. White worked until a theme turned up whose
    accent is #fafafa — and then the mark was white on white, which is what "the logo looks wrong"
    meant. The ink token exists precisely because it is the colour guaranteed to read on the
    accent, whatever the accent happens to be.
--}}
<svg {{ $attributes->class($class) }} viewBox="0 0 64 64" role="img" aria-label="{{ config('app.name') }}">
    <defs>
        <linearGradient id="{{ $id }}-taktiBadge" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="var(--color-accent-2)"/>
            <stop offset="52%" stop-color="var(--color-accent)"/>
            <stop offset="100%" stop-color="var(--color-accent-deep, var(--color-accent))"/>
        </linearGradient>

        {{-- the light that makes a flat square look like a physical tile --}}
        <linearGradient id="{{ $id }}-taktiGloss" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#fff" stop-opacity=".26"/>
            <stop offset="60%" stop-color="#fff" stop-opacity="0"/>
        </linearGradient>

        <radialGradient id="{{ $id }}-taktiDrop" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#1a1035" stop-opacity=".45"/>
            <stop offset="100%" stop-color="#1a1035" stop-opacity="0"/>
        </radialGradient>
    </defs>

    <rect x="1" y="1" width="62" height="62" rx="15" fill="url(#{{ $id }}-taktiBadge)"/>
    <rect x="1" y="1" width="62" height="30" rx="15" fill="url(#{{ $id }}-taktiGloss)"/>

    {{-- the shadow Takti casts on the tile, which is what lifts him off it --}}
    <ellipse cx="32" cy="52" rx="17" ry="4.5" fill="url(#{{ $id }}-taktiDrop)"/>

    {{-- the figure, filling the tile the way an icon should --}}
    <g fill="var(--color-accent-ink)">
        <g stroke="var(--color-accent-ink)" stroke-width="4.4" stroke-linecap="round" fill="none">
            <path d="M18 34 L12.5 39"/>
            <path d="M46 34 L51.5 39"/>
        </g>

        <ellipse cx="25" cy="50" rx="5.4" ry="3"/>
        <ellipse cx="39" cy="50" rx="5.4" ry="3"/>

        <rect x="15" y="14" width="34" height="34" rx="11.5"/>
    </g>

    {{-- the clock hand: the one line that keeps the outline from being a rounded square --}}
    <path d="M32 14 l4.5 -5.5" stroke="var(--color-accent-ink)" stroke-width="3.2" stroke-linecap="round" fill="none"/>

    {{-- the face, in the brand colour rather than black, so it belongs to the tile --}}
    <g fill="var(--color-accent)">
        <circle cx="25.5" cy="30" r="3.3"/>
        <circle cx="38.5" cy="30" r="3.3"/>
        <path d="M28 38.5 q4 3.2 8 0" stroke="var(--color-accent)" stroke-width="2.3"
              stroke-linecap="round" fill="none"/>
    </g>

    {{-- two catchlights, the cheapest thing that makes a face look alive --}}
    <g fill="var(--color-accent-ink)" fill-opacity=".92">
        <circle cx="26.6" cy="28.8" r="1.05"/>
        <circle cx="39.6" cy="28.8" r="1.05"/>
    </g>
</svg>
