@props([
    'pose' => 'idle',
    'class' => 'size-16',
    'label' => null,
])

@php
    /*
     * Takti — one figure, many poses, one file.
     *
     * The shape is not invented next to the brand, it IS the brand: the logo's rounded square
     * became a body and its two dots became the eyes they always looked like. The clock hand on
     * top is the only addition, and it is what keeps the silhouette readable at 16 pixels — a
     * character whose outline needs colour to be recognised is not a mascot, it is an image.
     *
     * Poses are DATA, not a switch per pose. Thirty-odd @case blocks would be unreadable and
     * would drift; here every pose is one row saying which hand angle, arms, eyes, mouth and prop
     * it uses, and the markup below is written once.
     */
    $poses = [
        //                 hand        arms      eyes      mouth      tone
        'idle' =>        ['rest',     'down',   'open',   'soft',    'accent'],
        'working' =>     ['up',       'down',   'open',   'flat',    'work'],
        'break' =>       ['right',    'cup',    'open',   'smile',   'rest'],
        'done' =>        ['side',     'down',   'closed', 'smile',   'accent'],
        'over' =>        ['left',     'hips',   'wide',   'frown',   'danger'],
        'cheer' =>       ['up-right', 'up',     'happy',  'grin',    'work'],
        'sleep' =>       ['side',     'down',   'closed', 'snore',   'accent'],
        'search' =>      ['rest',     'glass',  'open',   'soft',    'accent'],
        'error' =>       ['left',     'down',   'wide',   'frown',   'danger'],
        'empty' =>       ['rest',     'shrug',  'open',   'soft',    'accent'],

        // one per area of the app — the pose says where you are
        'code' =>        ['up',       'type',   'open',   'flat',    'accent'],
        'calendar' =>    ['rest',     'hold',   'open',   'soft',    'accent'],
        'vacation' =>    ['right',    'down',   'shades', 'smile',   'rest'],
        'note' =>        ['rest',     'write',  'open',   'flat',    'accent'],
        'package' =>     ['side',     'carry',  'open',   'smile',   'work'],
        'whale' =>       ['up',       'down',   'open',   'smile',   'accent'],
        'ticket' =>      ['rest',     'hold',   'open',   'smile',   'accent'],
        'chart' =>       ['up-right', 'point',  'open',   'flat',    'work'],
        'clean' =>       ['left',     'broom',  'open',   'flat',    'accent'],
        'build' =>       ['up',       'tool',   'open',   'smile',   'accent'],
        'run' =>         ['left',     'run',    'wide',   'flat',    'work'],
        'gear' =>        ['right',    'tool',   'open',   'soft',    'accent'],
        'tag' =>         ['rest',     'hold',   'open',   'soft',    'accent'],
        'rocket' =>      ['up',       'up',     'happy',  'grin',    'work'],
        'send' =>        ['up-right', 'throw',  'open',   'smile',   'accent'],
        'box' =>         ['side',     'carry',  'open',   'soft',    'accent'],
        'clock' =>       ['up',       'point',  'open',   'soft',    'accent'],
        'stack' =>       ['rest',     'carry',  'open',   'soft',    'accent'],
        'wave' =>        ['right',    'wave',   'happy',  'grin',    'accent'],
        'point' =>       ['side',     'point',  'open',   'soft',    'accent'],
        'think' =>       ['left',     'chin',   'squint', 'flat',    'accent'],
        'read' =>        ['rest',     'book',   'closed', 'soft',    'accent'],
        'wait' =>        ['right',    'hips',   'squint', 'flat',    'accent'],
        'trophy' =>      ['up-right', 'hold',   'happy',  'grin',    'work'],
    ];

    [$hand, $arms, $eyes, $mouth, $toneName] = $poses[$pose] ?? $poses['idle'];

    $tone = match ($toneName) {
        'work' => 'var(--color-work)',
        'rest' => 'var(--color-rest)',
        'danger' => 'var(--color-danger)',
        default => 'var(--color-accent-2)',
    };

    $hands = [
        'up' => 'M32 14 V5',
        'rest' => 'M32 14 l5 -6',
        'side' => 'M32 14 l8 -1',
        'right' => 'M32 14 l7 -5',
        'left' => 'M32 14 l-8 -3',
        'up-right' => 'M32 14 l6 -7',
    ];

    $id = 'takti-'.$pose.'-'.substr(md5($pose.$class), 0, 6);
@endphp

<svg {{ $attributes->class($class) }} viewBox="0 0 64 64" role="img"
     aria-label="{{ $label ?? __('app.mascot.pose.'.$pose) }}" data-mascot="{{ $pose }}"
     data-arms="{{ $arms }}">
    <defs>
        {{--
            Volume, not a flat fill. The light sits up and to the left, so the body is lit there and
            falls away to the lower right — that one decision is most of the difference between a
            figure and a coloured shape. A linear gradient cannot do it; a radial one placed off
            centre can, and costs nothing extra.
        --}}
        <radialGradient id="{{ $id }}" cx="32%" cy="22%" r="92%">
            <stop offset="0%" stop-color="var(--color-accent-2)"/>
            <stop offset="45%" stop-color="var(--color-accent)"/>
            <stop offset="100%" stop-color="var(--color-accent-deep, var(--color-accent))"/>
        </radialGradient>

        {{-- the sheen across the top of the head, fading out before it reaches the middle --}}
        <linearGradient id="{{ $id }}-sheen" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#fff" stop-opacity=".30"/>
            <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
        </linearGradient>

        {{-- a soft contact shadow, done as a gradient because a blur filter costs a repaint --}}
        <radialGradient id="{{ $id }}-shadow" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#000" stop-opacity=".28"/>
            <stop offset="70%" stop-color="#000" stop-opacity=".08"/>
            <stop offset="100%" stop-color="#000" stop-opacity="0"/>
        </radialGradient>
    </defs>

    {{-- the shadow he stands in --}}
    <ellipse cx="32" cy="59.5" rx="19" ry="4" fill="url(#{{ $id }}-shadow)" class="mascot-shadow"/>

    {{-- the hand on his head: it points where the pose points --}}
    <g stroke="{{ $tone }}" stroke-width="3" stroke-linecap="round" fill="none" class="mascot-hand">
        <path d="{{ $hands[$hand] }}"/>
    </g>

    {{-- props that sit BEHIND the body --}}
    @if ($arms === 'whale' || $pose === 'whale')
        <g class="mascot-prop mascot-prop-back" fill="var(--color-accent)" opacity=".85">
            <path d="M6 52 q10 8 26 8 t26 -8 q-4 -6 -26 -6 t-26 6 z"/>
            <path d="M4 48 l-3 -7 l8 3 z"/>
        </g>
    @endif

    {{-- arms --}}
    <g stroke="url(#{{ $id }})" stroke-width="5" stroke-linecap="round" fill="none" class="mascot-arms">
        @switch($arms)
            @case('up') <path d="M15 34 L8 21"/><path d="M49 34 L56 21"/> @break
            @case('hips') <path d="M15 35 L9 41"/><path d="M49 35 L55 41"/> @break
            @case('shrug') <path d="M15 34 L7 30"/><path d="M49 34 L57 30"/> @break
            @case('point') <path d="M15 36 L9 41"/><path d="M49 33 L60 26"/> @break
            @case('type') <path d="M17 38 L13 45"/><path d="M47 38 L51 45"/> @break
            @case('carry') <path d="M16 35 L12 29"/><path d="M48 35 L52 29"/> @break
            @case('run') <path d="M15 33 L6 29"/><path d="M49 37 L57 44"/> @break
            @case('wave') <path d="M15 36 L9 41"/><path d="M49 32 L58 22"/> @break
            @case('chin') <path d="M15 36 L9 41"/><path d="M47 38 L38 44"/> @break
            @case('throw') <path d="M15 36 L9 41"/><path d="M49 30 L57 20"/> @break
            @case('broom') <path d="M15 36 L9 41"/><path d="M48 34 L55 38"/> @break
            @case('tool') <path d="M15 36 L9 41"/><path d="M49 34 L57 30"/> @break
            @case('write') <path d="M15 37 L10 43"/><path d="M47 39 L52 46"/> @break
            @case('book') <path d="M16 38 L12 44"/><path d="M48 38 L52 44"/> @break
            @case('hold') <path d="M16 36 L12 41"/><path d="M48 36 L52 41"/> @break
            @case('glass') <path d="M15 36 L8 40"/><path d="M49 34 L58 28"/> @break
            @case('cup') <path d="M15 36 L8 40"/><path d="M49 36 L54 42"/> @break
            @default <path d="M15 36 L9 41"/><path d="M49 36 L55 41"/>
        @endswitch
    </g>

    {{-- feet --}}
    <g fill="url(#{{ $id }})" class="mascot-feet">
        @if ($arms === 'run')
            <ellipse cx="20" cy="57" rx="6.5" ry="3.2" transform="rotate(-18 20 57)"/>
            <ellipse cx="42" cy="56" rx="6.5" ry="3.2" transform="rotate(14 42 56)"/>
        @else
            <ellipse cx="24" cy="57" rx="6" ry="3.4"/>
            <ellipse cx="40" cy="57" rx="6" ry="3.4"/>
        @endif
    </g>

    {{-- the body: the logo, grown up --}}
    <rect x="12" y="14" width="40" height="40" rx="13" fill="url(#{{ $id }})"/>

    {{-- a hairline of light along the top edge, and the sheen under it --}}
    <rect x="12" y="14" width="40" height="22" rx="13" fill="url(#{{ $id }}-sheen)"/>
    <path d="M19 15.5 q13 -3 26 0" stroke="#fff" stroke-opacity=".38" stroke-width="1.2"
          fill="none" stroke-linecap="round"/>

    {{-- eyes --}}
    <g fill="var(--color-accent-ink)" class="mascot-eyes">
        @switch($eyes)
            @case('closed')
                <path d="M21 32 q4 4 8 0" stroke="var(--color-accent-ink)" stroke-width="2.6" fill="none" stroke-linecap="round"/>
                <path d="M35 32 q4 4 8 0" stroke="var(--color-accent-ink)" stroke-width="2.6" fill="none" stroke-linecap="round"/>
                @break
            @case('happy')
                <path d="M21 33 q4 -5 8 0" stroke="var(--color-accent-ink)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
                <path d="M35 33 q4 -5 8 0" stroke="var(--color-accent-ink)" stroke-width="2.8" fill="none" stroke-linecap="round"/>
                @break
            @case('wide')
                <circle cx="25" cy="32" r="4.6"/><circle cx="39" cy="32" r="4.6"/>
                @break
            @case('squint')
                <path d="M21 32 h8" stroke="var(--color-accent-ink)" stroke-width="2.8" stroke-linecap="round"/>
                <path d="M35 32 h8" stroke="var(--color-accent-ink)" stroke-width="2.8" stroke-linecap="round"/>
                @break
            @case('shades')
                <rect x="19" y="28" width="11" height="8" rx="3" fill="var(--color-accent-ink)"/>
                <rect x="34" y="28" width="11" height="8" rx="3" fill="var(--color-accent-ink)"/>
                <path d="M30 31 h4" stroke="var(--color-accent-ink)" stroke-width="2"/>
                @break
            @default
                <circle cx="25" cy="32" r="3.6"/><circle cx="39" cy="32" r="3.6"/>
        @endswitch
    </g>

    @if (in_array($eyes, ['open', 'wide'], true))
        {{-- catchlights: two dots of nothing that decide whether a face is alive or printed --}}
        <g fill="#fff" fill-opacity=".85" class="mascot-spark">
            <circle cx="{{ $eyes === 'wide' ? 26.4 : 26.2 }}" cy="30.6" r="{{ $eyes === 'wide' ? 1.5 : 1.15 }}"/>
            <circle cx="{{ $eyes === 'wide' ? 40.4 : 40.2 }}" cy="30.6" r="{{ $eyes === 'wide' ? 1.5 : 1.15 }}"/>
        </g>
    @endif

    {{-- mouth: the whole personality sits in eight pixels --}}
    <g stroke="var(--color-accent-ink)" stroke-width="2.4" stroke-linecap="round" fill="none" class="mascot-mouth">
        @switch($mouth)
            @case('grin') <path d="M26 41 q6 7 12 0" stroke-width="2.8"/> @break
            @case('flat') <path d="M28 42 h8"/> @break
            @case('smile') <path d="M27 41 q5 4 10 0"/> @break
            @case('frown') <path d="M27 43 q5 -4 10 0"/> @break
            @case('snore') <ellipse cx="32" cy="42" rx="2.6" ry="3.2" fill="var(--color-accent-ink)" stroke="none"/> @break
            @default <path d="M28 41 q4 3 8 0"/>
        @endswitch
    </g>

    {{-- props in front --}}
    <g class="mascot-prop">
        @switch($pose)
            @case('break')
                <rect x="50" y="38" width="9" height="8" rx="2" fill="var(--color-rest)"/>
                <path d="M59 40 q3 2 0 4" stroke="var(--color-rest)" stroke-width="2" fill="none"/>
                <path d="M53 35 q1.5 -3 0 -5" stroke="var(--color-rest)" stroke-width="1.6" fill="none" stroke-linecap="round" opacity=".7"/>
                @break
            @case('search')
                <circle cx="58" cy="26" r="5" fill="none" stroke="var(--color-accent-ink)" stroke-width="2.6"/>
                <path d="M54 30 l-4 4" stroke="var(--color-accent-ink)" stroke-width="2.6" stroke-linecap="round"/>
                @break
            @case('sleep')
                <text x="50" y="20" font-size="10" fill="var(--color-accent-2)" opacity=".8">z</text>
                <text x="56" y="13" font-size="7" fill="var(--color-accent-2)" opacity=".6">z</text>
                @break
            @case('cheer')
                <circle cx="10" cy="16" r="2" fill="var(--color-work)"/>
                <circle cx="54" cy="14" r="1.6" fill="var(--color-work)"/>
                <circle cx="46" cy="8" r="1.3" fill="var(--color-work)"/>
                @break
            @case('error')
                <path d="M54 14 v7" stroke="var(--color-danger)" stroke-width="3" stroke-linecap="round"/>
                <circle cx="54" cy="26" r="1.8" fill="var(--color-danger)"/>
                @break
            @case('code')
                <text x="4" y="30" font-size="11" font-family="ui-monospace, monospace" fill="var(--color-accent-2)" opacity=".85">&lt;</text>
                <text x="54" y="30" font-size="11" font-family="ui-monospace, monospace" fill="var(--color-accent-2)" opacity=".85">&gt;</text>
                <rect x="14" y="46" width="36" height="4" rx="2" fill="var(--color-accent-ink)" opacity=".25"/>
                @break
            @case('calendar')
                <rect x="46" y="30" width="15" height="14" rx="2.5" fill="var(--color-canvas)" stroke="var(--color-accent-2)" stroke-width="2"/>
                <path d="M46 35 h15" stroke="var(--color-accent-2)" stroke-width="2"/>
                <path d="M50 28 v4 M57 28 v4" stroke="var(--color-accent-2)" stroke-width="2" stroke-linecap="round"/>
                @break
            @case('vacation')
                <path d="M54 44 v-10" stroke="var(--color-work)" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M54 34 q-6 -3 -8 2 M54 34 q6 -3 8 2 M54 34 q0 -6 5 -6" stroke="var(--color-work)" stroke-width="2.2" fill="none" stroke-linecap="round"/>
                <circle cx="9" cy="16" r="5" fill="var(--color-rest)" opacity=".85"/>
                @break
            @case('note')
                <rect x="47" y="40" width="12" height="15" rx="2" fill="var(--color-canvas)" stroke="var(--color-accent-2)" stroke-width="1.8"/>
                <path d="M50 45 h6 M50 49 h6" stroke="var(--color-accent-2)" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M52 46 l7 -8" stroke="var(--color-rest)" stroke-width="2.6" stroke-linecap="round"/>
                @break
            @case('package')
            @case('box')
                <rect x="22" y="20" width="20" height="16" rx="2.5" fill="{{ $pose === 'package' ? 'var(--color-work)' : 'var(--color-accent-2)' }}" opacity=".9"/>
                <path d="M32 20 v16 M22 27 h20" stroke="var(--color-canvas)" stroke-width="2" opacity=".55"/>
                @break
            @case('ticket')
                <g transform="rotate(-8 52 38)">
                    <rect x="45" y="32" width="16" height="11" rx="2.5" fill="var(--color-canvas)" stroke="var(--color-accent-2)" stroke-width="1.8"/>
                    <path d="M50 32 v11" stroke="var(--color-accent-2)" stroke-width="1.5" stroke-dasharray="2 2"/>
                </g>
                @break
            @case('chart')
                <g fill="var(--color-work)">
                    <rect x="50" y="34" width="3.5" height="10" rx="1.2" opacity=".6"/>
                    <rect x="55" y="28" width="3.5" height="16" rx="1.2" opacity=".8"/>
                    <rect x="60" y="22" width="3.5" height="22" rx="1.2"/>
                </g>
                @break
            @case('clean')
                <path d="M56 36 l4 -10" stroke="var(--color-accent-2)" stroke-width="2.4" stroke-linecap="round"/>
                <path d="M52 38 l8 2 l-2 6 l-8 -2 z" fill="var(--color-rest)" opacity=".85"/>
                @break
            @case('build')
                <rect x="50" y="26" width="9" height="9" rx="2" fill="var(--color-accent-2)"/>
                <rect x="54" y="35" width="9" height="9" rx="2" fill="var(--color-work)" opacity=".85"/>
                @break
            @case('gear')
                <g fill="none" stroke="var(--color-accent-2)" stroke-width="2.4">
                    <circle cx="57" cy="28" r="4.5"/>
                    <path d="M57 20 v3 M57 33 v3 M49 28 h3 M62 28 h3" stroke-linecap="round"/>
                </g>
                @break
            @case('tag')
                <g transform="rotate(-20 52 36)">
                    <path d="M46 30 h10 l5 6 -5 6 h-10 z" fill="var(--color-accent-2)" opacity=".9"/>
                    <circle cx="50" cy="36" r="1.6" fill="var(--color-canvas)"/>
                </g>
                @break
            @case('rocket')
                <path d="M57 32 q4 -9 0 -16 q-4 7 0 16 z" fill="var(--color-danger)" opacity=".9"/>
                <path d="M55 32 q2 5 4 0" fill="var(--color-rest)"/>
                @break
            @case('send')
                <path d="M46 24 L62 18 L54 32 L52 26 z" fill="var(--color-accent-2)" opacity=".9"/>
                @break
            @case('clock')
                <circle cx="56" cy="26" r="6.5" fill="var(--color-canvas)" stroke="var(--color-accent-2)" stroke-width="2"/>
                <path d="M56 26 V21 M56 26 l3.5 2" stroke="var(--color-accent-2)" stroke-width="2" stroke-linecap="round" class="mascot-needle"/>
                @break
            @case('stack')
                <g fill="var(--color-accent-2)" opacity=".85">
                    <rect x="22" y="30" width="20" height="4" rx="2"/>
                    <rect x="22" y="24" width="20" height="4" rx="2" opacity=".75"/>
                    <rect x="22" y="18" width="20" height="4" rx="2" opacity=".5"/>
                </g>
                @break
            @case('think')
                <g fill="var(--color-accent-2)" opacity=".8">
                    <circle cx="50" cy="20" r="2"/><circle cx="55" cy="15" r="3"/><circle cx="61" cy="9" r="4"/>
                </g>
                @break
            @case('read')
                <path d="M20 40 q12 -5 24 0 v10 q-12 -5 -24 0 z" fill="var(--color-canvas)" stroke="var(--color-accent-2)" stroke-width="1.8"/>
                <path d="M32 42 v10" stroke="var(--color-accent-2)" stroke-width="1.6"/>
                @break
            @case('wait')
                <g fill="var(--color-accent-2)" opacity=".8">
                    <circle cx="52" cy="20" r="1.6"/><circle cx="57" cy="20" r="1.6"/><circle cx="62" cy="20" r="1.6"/>
                </g>
                @break
            @case('trophy')
                <path d="M52 18 h10 v6 q0 5 -5 5 t-5 -5 z" fill="var(--color-rest)"/>
                <path d="M57 29 v5 M53 34 h8" stroke="var(--color-rest)" stroke-width="2.4" stroke-linecap="round"/>
                @break
            @case('wave')
                <g fill="none" stroke="var(--color-accent-2)" stroke-width="1.8" opacity=".7" stroke-linecap="round">
                    <path d="M60 18 q3 -2 3 -5"/><path d="M56 15 q2 -3 1 -6"/>
                </g>
                @break
            @case('run')
                <g stroke="var(--color-accent-2)" stroke-width="2" opacity=".55" stroke-linecap="round">
                    <path d="M4 30 h7"/><path d="M2 38 h6"/><path d="M5 46 h5"/>
                </g>
                @break
        @endswitch
    </g>
</svg>
