@props(['name', 'class' => 'size-5'])

@php
    $attributes = $attributes->class($class);
@endphp

@switch($name)
    @case('play')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M8 5.14v13.72c0 .82.9 1.32 1.6.89l10.9-6.86a1.05 1.05 0 0 0 0-1.78L9.6 4.25A1.05 1.05 0 0 0 8 5.14Z"/>
        </svg>
        @break

    @case('pause')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M8 4.5A1.5 1.5 0 0 1 9.5 6v12a1.5 1.5 0 0 1-3 0V6A1.5 1.5 0 0 1 8 4.5Zm8 0A1.5 1.5 0 0 1 17.5 6v12a1.5 1.5 0 0 1-3 0V6A1.5 1.5 0 0 1 16 4.5Z"/>
        </svg>
        @break

    @case('stop')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <rect x="6" y="6" width="12" height="12" rx="2.5"/>
        </svg>
        @break

    @case('coffee')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 8h13v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8Z"/>
            <path d="M17 9h1.5a2.5 2.5 0 0 1 0 5H17"/>
            <path d="M6 3.5c0 .8.6 1.2.6 2M10 3.5c0 .8.6 1.2.6 2M14 3.5c0 .8.6 1.2.6 2"/>
        </svg>
        @break

    @case('briefcase')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="7.5" width="18" height="12" rx="2.5"/>
            <path d="M9 7.5V6a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v1.5M3 12.5h18"/>
        </svg>
        @break

    @case('plus')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M12 5v14M5 12h14"/>
        </svg>
        @break

    @case('pencil')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 20h4l10.5-10.5a2.5 2.5 0 0 0-3.5-3.5L4.5 16.5V20Z"/>
            <path d="M14.5 6.5 17.5 9.5"/>
        </svg>
        @break

    @case('trash')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 7h16M9.5 7V5.5A1.5 1.5 0 0 1 11 4h2a1.5 1.5 0 0 1 1.5 1.5V7"/>
            <path d="M6.5 7l.8 11.2A2 2 0 0 0 9.3 20h5.4a2 2 0 0 0 2-1.8L17.5 7"/>
        </svg>
        @break

    @case('chevron-left')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14.5 6 8.5 12l6 6"/>
        </svg>
        @break

    @case('chevron-right')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9.5 6l6 6-6 6"/>
        </svg>
        @break

    @case('calendar')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3.5" y="5" width="17" height="15" rx="2.5"/>
            <path d="M3.5 10h17M8 3.5V6M16 3.5V6"/>
        </svg>
        @break

    @case('check')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 13l4.5 4.5L19 7"/>
        </svg>
        @break

    @case('arrow-left')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 6l-6 6 6 6M5 12h14"/>
        </svg>
        @break

    @case('scale')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4.5v15M7 19.5h10"/>
            <path d="M12 7 5 8.5 2.5 14a3.6 3.6 0 0 0 5 0L5 8.5M12 7l7 1.5L21.5 14a3.6 3.6 0 0 1-5 0L19 8.5"/>
        </svg>
        @break

    @case('list-check')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 7.5l2 2 3.5-3.5M4 17l2 2 3.5-3.5M13 8h7M13 18h7"/>
        </svg>
        @break

    @case('gear')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 7h9M17.5 7H20M4 12h3.5M12 12h8M4 17h9M17.5 17H20"/>
            <circle cx="15" cy="7" r="2.1"/>
            <circle cx="9.5" cy="12" r="2.1"/>
            <circle cx="15" cy="17" r="2.1"/>
        </svg>
        @break

    @case('logout')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M15 5.5H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-3"/>
            <path d="M11 8.5 7.5 12l3.5 3.5M7.5 12H16"/>
        </svg>
        @break

    @case('lock')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="5" y="10.5" width="14" height="9.5" rx="2.5"/>
            <path d="M8.5 10.5V8a3.5 3.5 0 0 1 7 0v2.5"/>
        </svg>
        @break

    @case('user')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="8.5" r="3.5"/>
            <path d="M5 20c.8-3.4 3.6-5 7-5s6.2 1.6 7 5"/>
        </svg>
        @break

    @case('swatch')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4A1.5 1.5 0 0 1 11 5.5V17a3.5 3.5 0 0 1-7 0V5.5Z"/>
            <path d="M11 9.5l3-3a1.5 1.5 0 0 1 2.1 0l2.9 2.9a1.5 1.5 0 0 1 0 2.1L11 18.5"/>
            <path d="M7.5 17h.01"/>
        </svg>
        @break


    @case('alert')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4.5 3 19.5h18L12 4.5Z"/>
            <path d="M12 10v4.2M12 17h.01"/>
        </svg>
        @break

    @case('tag')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 11.5V5.5A1.5 1.5 0 0 1 5.5 4h6l8 8-7.5 7.5L4 11.5Z"/>
            <path d="M8 8h.01"/>
        </svg>
        @break

    @case('repeat')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 8.5A3.5 3.5 0 0 1 7.5 5H17l-2.5-2.5M20 15.5A3.5 3.5 0 0 1 16.5 19H7l2.5 2.5"/>
            <path d="M20 8.5v7M4 8.5v7"/>
        </svg>
        @break

    @case('paperclip')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M16.5 6.5 8.7 14.3a2.6 2.6 0 0 0 3.7 3.7l7.1-7.1a4.5 4.5 0 0 0-6.4-6.4l-7.3 7.3a6.4 6.4 0 0 0 9 9l4.2-4.2"/>
        </svg>
        @break

    @case('calendar-days')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3.5" y="5" width="17" height="15" rx="2.5"/>
            <path d="M3.5 10h17M8 3.5V6M16 3.5V6"/>
            <path d="M7.5 13.5h2M11 13.5h2M14.5 13.5h2M7.5 16.5h2M11 16.5h2"/>
        </svg>
        @break

    @case('printer')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 9V4.5h10V9M7 17H5.5A1.5 1.5 0 0 1 4 15.5v-5A1.5 1.5 0 0 1 5.5 9h13a1.5 1.5 0 0 1 1.5 1.5v5A1.5 1.5 0 0 1 18.5 17H17"/>
            <rect x="7" y="13.5" width="10" height="6" rx="1.2"/>
        </svg>
        @break

    @case('download')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4v10M8.5 10.5 12 14l3.5-3.5"/>
            <path d="M5 17.5V19a1.5 1.5 0 0 0 1.5 1.5h11A1.5 1.5 0 0 0 19 19v-1.5"/>
        </svg>
        @break

    @case('search')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="6.5"/>
            <path d="m15.8 15.8 3.7 3.7"/>
        </svg>
        @break

    @case('close')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6.5 6.5l11 11M17.5 6.5l-11 11"/>
        </svg>
        @break

    @case('clock')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="8.5"/>
            <path d="M12 7.5V12l3 2"/>
        </svg>
        @break
    @case('chart')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 20h16"/>
            <path d="M7 20v-6M12 20V8M17 20v-9"/>
        </svg>
        @break

    @case('grid')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/>
            <rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/>
            <rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/>
            <rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>
        </svg>
        @break
    @case('panel')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3.25" y="4.25" width="17.5" height="15.5" rx="3"/>
            <path d="M9.75 4.5v15"/>
        </svg>
        @break
    @case('chevron-down')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 9.5l6 6 6-6"/>
        </svg>
        @break
    @case('terminal')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 9l3 3-3 3"/>
            <path d="M12.5 15h5"/>
            <rect x="2.75" y="4.25" width="18.5" height="15.5" rx="3"/>
        </svg>
        @break
    @case('info')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/>
            <path d="M12 11v5.5"/>
            <path d="M12 7.6h.01"/>
        </svg>
        @break
    @case('send')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4.5 12l15.5-7-7 15.5-2.2-6.3z"/>
            <path d="M10.8 14.2 20 5"/>
        </svg>
        @break
    @case('minus')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
            <path d="M6.5 12h11"/>
        </svg>
        @break
    @case('grip')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/>
            <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
            <circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/>
        </svg>
        @break
    @case('squares')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3.25" y="3.25" width="7.5" height="7.5" rx="2"/>
            <rect x="13.25" y="3.25" width="7.5" height="7.5" rx="2"/>
            <rect x="3.25" y="13.25" width="7.5" height="7.5" rx="2"/>
            <rect x="13.25" y="13.25" width="7.5" height="7.5" rx="2"/>
        </svg>
        @break
    @case('chevron-up')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 14.5l6-6 6 6"/>
        </svg>
        @break
    @case('external')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M14 4h6v6"/>
            <path d="M20 4l-8.5 8.5"/>
            <path d="M18 14v4.5A1.5 1.5 0 0 1 16.5 20h-11A1.5 1.5 0 0 1 4 18.5v-11A1.5 1.5 0 0 1 5.5 6H10"/>
        </svg>
        @break
    @case('clipboard')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M9 4.5h6M9 4.5a1.5 1.5 0 0 0-1.5 1.5v.5h9V6A1.5 1.5 0 0 0 15 4.5"/>
            <path d="M7.5 6.5H6.5A1.5 1.5 0 0 0 5 8v10.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V8a1.5 1.5 0 0 0-1.5-1.5h-1"/>
        </svg>
        @break

    @case('home')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3.5 10.5 12 4l8.5 6.5V19a1.5 1.5 0 0 1-1.5 1.5h-4v-6h-6v6H5A1.5 1.5 0 0 1 3.5 19v-8.5Z"/>
        </svg>
        @break

    @case('folder')
        <svg {{ $attributes }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.1a2 2 0 0 1 1.6.8l.9 1.2h7.4A2.5 2.5 0 0 1 21 9.5v7A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5z"/>
        </svg>
        @break
@endswitch
