@props(['active'])

@php
    $tabs = [
        'dev' => __('app.dev.overview'),
        'projects' => __('app.dev.projects'),
        'commands' => __('app.dev.commands'),
        'docker' => __('app.docker.title'),
        'snippets' => __('app.dev.snippets'),
        'dev.testpost' => __('app.dev.testpost'),
    ];
@endphp

<div class="tile flex flex-wrap items-center gap-1 p-1">
    @foreach ($tabs as $route => $label)
        <a href="{{ route($route) }}"
           @class([
               'rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition',
               'bg-hover text-ink' => $active === $route,
               'text-muted hover:text-ink' => $active !== $route,
           ])>
            {{ $label }}
        </a>
    @endforeach
</div>
