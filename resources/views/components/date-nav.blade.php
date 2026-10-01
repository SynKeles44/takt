@props([
    'previous',
    'next',
    'current',
    'label',
    'position' => 'now',
    'markerKey',
    'partial' => null,
    'previousLabel' => null,
    'nextLabel' => null,
])

{{--
    Back, now, forward — the three buttons every dated page carries.

    The marker says where you are relative to today rather than what you last clicked: on the
    current period it sits on the middle button, in the past on the back arrow, in the future on
    the forward one. That is the only reading that holds, because stepping forward out of the past
    can land in the past again — and a marker that followed the click would then be wrong.

    The middle button is a span on the current period: there is nowhere to go, so it is a state,
    not a link. It keeps its full weight — the marker behind it is what says "you are here", which
    reads better than the dimmed-out button it replaces.
--}}
@php
    $step = 'relative inline-flex items-center rounded-[var(--radius-control)] transition';
    $arrow = $step.' p-[0.35rem] text-muted hover:text-ink';
    $middle = $step.' px-3 py-1.5 text-xs font-semibold';
@endphp

<div class="tile flex items-center gap-1 p-1"
     data-marker-row data-marker-key="{{ $markerKey }}" data-marker-item="[data-date-step]"
     data-marker-optimistic="false" data-marker-at="{{ $position }}">
    <a href="{{ $previous }}" class="{{ $arrow }}" data-date-step
       @if ($partial) data-partial="{{ $partial }}" @endif
       aria-label="{{ $previousLabel ?? __('app.nav.step_back') }}">
        @if ($position === 'past')<span class="tab-marker" aria-hidden="true"></span>@endif
        <x-icon name="chevron-left" class="relative size-4"/>
    </a>

    @if ($position === 'now')
        <span class="{{ $middle }} text-ink" data-date-step aria-current="page">
            <span class="tab-marker" aria-hidden="true"></span>
            <span class="relative">{{ $label }}</span>
        </span>
    @else
        <a href="{{ $current }}" class="{{ $middle }} text-muted hover:text-ink" data-date-step
           @if ($partial) data-partial="{{ $partial }}" @endif>
            <span class="relative">{{ $label }}</span>
        </a>
    @endif

    <a href="{{ $next }}" class="{{ $arrow }}" data-date-step
       @if ($partial) data-partial="{{ $partial }}" @endif
       aria-label="{{ $nextLabel ?? __('app.nav.step_forward') }}">
        @if ($position === 'future')<span class="tab-marker" aria-hidden="true"></span>@endif
        <x-icon name="chevron-right" class="relative size-4"/>
    </a>
</div>
