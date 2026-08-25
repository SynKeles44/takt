@props(['class' => 'size-10'])

<svg {{ $attributes->class($class) }} viewBox="0 0 64 64" role="img" aria-label="{{ config('app.name') }}">
  <defs>
    <linearGradient id="taktBadge" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="var(--color-accent-2)"/>
      <stop offset="55%" stop-color="var(--color-accent)"/>
      <stop offset="100%" stop-color="var(--color-accent-2)"/>
    </linearGradient>
  </defs>

  <rect x="2" y="2" width="60" height="60" rx="16" fill="url(#taktBadge)"/>

  <g fill="var(--color-accent-ink)">
    <circle cx="20" cy="26" r="3.8"/>
    <circle cx="20" cy="38" r="3.8"/>
    <rect x="28.5" y="16" width="4.5" height="32" rx="2.25" fill-opacity="0.75"/>
    <rect x="38" y="16" width="10" height="32" rx="4"/>
  </g>
</svg>
