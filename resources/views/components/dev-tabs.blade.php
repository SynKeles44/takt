@props(['active'])

@php
    /*
     * Two levels, because three of these pages are about projects and only make sense inside one:
     * commands run in a project, packages belong to a project, releases come out of a project.
     * Flat, they were seven siblings with no relationship; nested, the top row says where you are
     * and the second says what you can do there.
     */
    $tabs = [
        'dev' => __('app.dev.overview'),
        'projects' => __('app.dev.projects'),
        'docker' => __('app.docker.title'),
        'snippets' => __('app.dev.snippets'),
        'dev.testpost' => __('app.dev.testpost'),
    ];

    $children = [
        'projects' => [
            'projects' => __('app.dev.registered'),
            'commands' => __('app.dev.commands'),
            'packages' => __('app.packages.title'),
            'releases' => __('app.dev.releases'),
        ],
    ];

    // a parent is active when it is the page, or when the page is one of its children
    $parent = collect($children)
        ->filter(fn (array $routes, string $route): bool => $route === $active || array_key_exists($active, $routes))
        ->keys()
        ->first();
@endphp

<div class="flex flex-col items-end gap-1.5">
    <div class="tile flex flex-wrap items-center gap-1 p-1">
        @foreach ($tabs as $route => $label)
            <a href="{{ route($route) }}" data-nav
               @class([
                   'rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition',
                   'bg-hover text-ink' => $active === $route || $parent === $route,
                   'text-muted hover:text-ink' => $active !== $route && $parent !== $route,
               ])>
                {{ $label }}

                @if (isset($children[$route]))
                    <x-icon name="chevron-down" class="ms-0.5 inline size-3 opacity-60"/>
                @endif
            </a>
        @endforeach
    </div>

    @if ($parent !== null)
        {{-- the second row only exists while you are inside that branch --}}
        <div class="flex flex-wrap items-center gap-1 rounded-[var(--radius-control)] border border-line/60 bg-raised/40 p-1"
             data-subnav>
            @foreach ($children[$parent] as $route => $label)
                <a href="{{ route($route) }}" data-nav
                   @class([
                       'rounded-[var(--radius-control)] px-2.5 py-1 text-[11px] font-medium transition',
                       'bg-hover text-ink' => $active === $route,
                       'text-faint hover:text-ink' => $active !== $route,
                   ])>
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endif
</div>
