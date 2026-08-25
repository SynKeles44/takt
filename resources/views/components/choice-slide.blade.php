@props(['case', 'position', 'shown'])

<div data-slide="{{ $case->value }}"
     data-label="{{ $case->label() }}"
     data-description="{{ $case->description() }}"
     data-position="{{ $position }}"
     @class(['is-off' => ! $shown])
     @if (! $shown) aria-hidden="true" @endif>
    {{ $slot }}
</div>
