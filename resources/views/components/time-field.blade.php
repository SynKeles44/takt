@props([
    'name',
    'value' => null,
    'id' => null,
    'required' => false,
    'form' => null,
])

@php
    /*
     * A time field that moves on when the hour is done.
     *
     * `input[type=time]` would be the obvious choice and is the reason this exists: its hour and
     * minute live in the shadow DOM, so when a browser decides not to advance after a single
     * digit, nothing on this side can make it. Measured in Chromium it advances after "7" and
     * waits after "1"; in Safari it does not advance at all. Two real inputs behave the same
     * everywhere, which is the whole point.
     *
     * The name stays on a hidden field holding `HH:MM`, so every form, validator and controller
     * that reads it is untouched.
     */
    $id ??= $name;
    [$hour, $minute] = array_pad(explode(':', (string) $value, 2), 2, '');
@endphp

<div {{ $attributes->class('control time-field') }} data-time-field>
    <input type="text" inputmode="numeric" autocomplete="off" maxlength="2"
           id="{{ $id }}" value="{{ $hour }}" placeholder="--"
           aria-label="{{ __('app.form.hours') }}" data-time-hour @required($required)>

    <span class="time-field-sep" aria-hidden="true">:</span>

    <input type="text" inputmode="numeric" autocomplete="off" maxlength="2"
           value="{{ $minute }}" placeholder="--"
           aria-label="{{ __('app.form.minutes') }}" data-time-minute @required($required)>

    <input type="hidden" name="{{ $name }}" value="{{ $value }}" data-time-value
           @if ($form) form="{{ $form }}" @endif>
</div>
