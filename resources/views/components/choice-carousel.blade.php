@props([
    'param',
    'field',
    'action',
    'previewed',
    'previous',
    'next',
    'position',
    'count',
    'active',
    'chooseLabel',
    'activeLabel',
    'previousLabel',
    'nextLabel',
])

@php $isActive = $previewed === $active; @endphp

<div class="mt-5" data-carousel data-param="{{ $param }}" data-active="{{ $active->value }}">
    <div class="flex items-stretch gap-2">
        <a href="{{ route('settings', [$param => $previous->value]) }}"
           class="btn btn-icon shrink-0 self-stretch px-2" data-step="-1" aria-label="{{ $previousLabel }}">
            <x-icon name="chevron-left" class="size-4"/>
        </a>

        <div class="carousel-stack min-w-0 flex-1">
            {{ $slot }}
        </div>

        <a href="{{ route('settings', [$param => $next->value]) }}"
           class="btn btn-icon shrink-0 self-stretch px-2" data-step="1" aria-label="{{ $nextLabel }}">
            <x-icon name="chevron-right" class="size-4"/>
        </a>
    </div>

    <div class="mt-4 flex min-h-11 flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-ink" data-slide-name>{{ $previewed->label() }}</p>
            <p class="mt-0.5 text-xs leading-snug text-faint" data-slide-text>{{ $previewed->description() }}</p>
        </div>
        <span class="metric shrink-0 text-xs text-dim">
            <span data-slide-index>{{ $position }}</span> / {{ $count }}
        </span>
    </div>

    <p class="btn btn-ghost mt-4 w-full cursor-default text-work-text @unless ($isActive) hidden @endunless" data-slide-active>
        <x-icon name="check" class="size-4"/>
        {{ $activeLabel }}
    </p>

    <form method="POST" action="{{ $action }}" class="mt-4 @if ($isActive) hidden @endif" data-slide-form>
        @csrf
        @method('PUT')
        <input type="hidden" name="{{ $field }}" value="{{ $previewed->value }}">
        <button type="submit" class="btn btn-primary w-full">
            <x-icon name="check" class="size-4"/>
            {{ $chooseLabel }}
        </button>
    </form>
</div>
