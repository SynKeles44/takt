@props(['type', 'running' => false])

@php
    $isWork = $type === \App\Enums\EntryType::Work;
    $tone = $isWork
        ? 'border-work/30 bg-work/10 text-work-text'
        : 'border-rest/30 bg-rest/10 text-rest-text';
@endphp

<span {{ $attributes->class(['pill', $tone]) }} title="{{ $type->label() }}">
    <x-icon :name="$isWork ? 'briefcase' : 'coffee'" class="size-3.5"/>
    <span class="hidden sm:inline">{{ $type->label() }}</span>
    @if ($running)
        <span class="relative ml-0.5 flex size-1.5">
            <span class="dot absolute inline-flex size-full animate-ping bg-current opacity-70"></span>
            <span class="dot relative inline-flex size-1.5 bg-current"></span>
        </span>
    @endif
</span>
