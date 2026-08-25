{{-- The container list on its own, so it can be refreshed without reloading the page. --}}
<div data-region="docker-list">
    @if (! $docker['ok'])
        <x-card class="rise">
            <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                {{ $docker['error'] }}
            </p>
        </x-card>
    @elseif ($docker['groups']->isEmpty())
        <x-card class="rise">
            <p class="text-sm text-dim">{{ __('app.docker.none') }}</p>
        </x-card>
    @else
        <div class="stack">
            @foreach ($docker['groups'] as $group)
                <x-card class="rise">
                    <details data-remember="docker.{{ Str::slug($group['project'] ?: 'standalone') }}" @if ($group['running'] > 0) open @endif>
                        <summary class="flex cursor-pointer flex-wrap items-center gap-3">
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-dim transition"/>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $group['label'] }}</span>
                                @if ($group['path'])
                                    <span class="metric block truncate text-[11px] text-dim">{{ $group['path'] }}</span>
                                @endif
                            </span>

                            <span @class([
                                    'pill shrink-0 text-[10px]',
                                    'border-work/40 bg-work/10 text-work-text' => $group['running'] > 0,
                                ])>{{ __('app.docker.summary', ['running' => $group['running'], 'total' => $group['total']]) }}</span>
                        </summary>

                        <div class="mt-4 space-y-1.5">
                            @foreach ($group['containers'] as $container)
                                <div class="row flex flex-wrap items-center gap-3 px-3 py-2">
                                    <span @class([
                                            'dot size-2 shrink-0',
                                            'bg-work' => $container['running'],
                                            'bg-line-strong' => ! $container['running'],
                                        ])></span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-ink">{{ $container['service'] }}</span>
                                        <span class="metric block truncate text-[11px] text-dim">
                                            {{ $container['image'] }} · {{ $container['status'] }}
                                        </span>
                                    </span>

                                    @foreach (array_slice($container['ports'], 0, 3) as $port)
                                        <a href="http://localhost:{{ $port['host'] }}" target="_blank"
                                           class="pill metric shrink-0 text-[10px] hover:text-ink">:{{ $port['host'] }}</a>
                                    @endforeach

                                    <span class="flex shrink-0 items-center gap-1">
                                        <button type="button" class="icon-action" data-docker-logs="{{ $container['short'] }}"
                                                aria-label="{{ __('app.docker.logs') }}" title="{{ __('app.docker.logs') }}">
                                            <x-icon name="terminal" class="size-4"/>
                                        </button>

                                        @if ($container['running'])
                                            <button type="button" class="icon-action" data-docker-action="restart" data-docker-id="{{ $container['short'] }}"
                                                    aria-label="{{ __('app.docker.restart') }}" title="{{ __('app.docker.restart') }}">
                                                <x-icon name="repeat" class="size-4"/>
                                            </button>
                                            <button type="button" class="icon-action" data-docker-action="stop" data-docker-id="{{ $container['short'] }}"
                                                    aria-label="{{ __('app.docker.stop') }}" title="{{ __('app.docker.stop') }}">
                                                <x-icon name="stop" class="size-4"/>
                                            </button>
                                        @else
                                            <button type="button" class="icon-action" data-docker-action="start" data-docker-id="{{ $container['short'] }}"
                                                    aria-label="{{ __('app.docker.start') }}" title="{{ __('app.docker.start') }}">
                                                <x-icon name="play" class="size-4"/>
                                            </button>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </details>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
