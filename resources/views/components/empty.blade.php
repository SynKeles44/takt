@props([
    'pose' => 'empty',
    'title' => null,
    'hint' => null,
    'compact' => false,
])

{{--
    An empty state, with Takti in it. The figure is not decoration here — an empty list is the
    one moment a person wonders whether the app is broken or simply has nothing to show, and a
    character that looks around says "nothing here" in a way a dashed rectangle never did.
--}}
<div {{ $attributes->class([
    'flex flex-col items-center justify-center gap-3 rounded-[var(--radius-control)] border border-dashed border-line text-center',
    'px-4 py-6' => $compact,
    'px-4 py-10' => ! $compact,
]) }}>
    <x-mascot :pose="$pose" :class="$compact ? 'size-16' : 'size-28'"/>

    <div>
        @if ($title)
            <p class="text-sm font-medium text-muted">{{ $title }}</p>
        @endif

        @if ($hint || ! $slot->isEmpty())
            <p class="mt-1 text-xs text-faint">{{ $hint ?? $slot }}</p>
        @endif
    </div>

    @if ($hint && ! $slot->isEmpty())
        <div class="text-xs text-faint">{{ $slot }}</div>
    @endif
</div>
