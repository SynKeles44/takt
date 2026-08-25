@props(['icon', 'title', 'text' => null, 'blank' => false])

<a {{ $attributes->class('nav-menu-item nav-menu-item-stacked') }} @if ($blank) target="_blank" @endif>
    <x-icon :name="$icon" class="mt-0.5 size-4 shrink-0"/>

    <span class="min-w-0">
        <span class="block text-sm font-medium text-ink">{{ $title }}</span>
        @if ($text)
            <span class="block text-[11px] leading-relaxed text-faint">{{ $text }}</span>
        @endif
    </span>
</a>
