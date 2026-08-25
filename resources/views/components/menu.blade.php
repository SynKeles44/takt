@props(['label', 'icon' => 'download', 'align' => 'right', 'width' => 'w-72'])

<div class="relative" data-menu-wrap>
    <button type="button" data-menu-toggle aria-haspopup="true" aria-expanded="false"
            class="btn btn-ghost text-xs">
        <x-icon :name="$icon" class="size-4"/>
        {{ $label }}
        <x-icon name="chevron-down" class="size-3.5 text-dim"/>
    </button>

    <div data-menu @class(['nav-menu absolute z-40 mt-2 hidden p-1.5', $width, 'right-0' => $align === 'right', 'left-0' => $align !== 'right'])>
        {{ $slot }}
    </div>
</div>
