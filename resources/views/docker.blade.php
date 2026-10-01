<x-app-layout :title="__('app.docker.title')" :wide="true" :defer="$defer">
    <x-dev-page :title="__('app.docker.title')" :hint="__('app.docker.hint')" active="docker">
        <x-slot:actions>
            @if (! $defer && $docker['ok'])
                <span class="pill">{{ __('app.docker.summary', ['running' => $docker['running'], 'total' => $docker['total']]) }}</span>
            @endif

            <button type="button" class="btn btn-icon" data-docker-refresh
                    aria-label="{{ __('app.docker.refresh') }}" title="{{ __('app.docker.refresh') }}">
                <x-icon name="repeat" class="size-4"/>
            </button>
        </x-slot:actions>
    </x-dev-page>

    <div class="mt-5" data-docker data-list-url="{{ route('docker.list') }}"
         data-act-url="{{ route('docker.act') }}" data-logs-url="{{ route('docker.logs') }}">
        @if ($defer)
            <x-defer-skeleton class="mt-0" :cards="3" :rows="3"/>
        @else
            @include('partials.docker-list', ['docker' => $docker])
        @endif
    </div>

    {{-- the same shape as a run: a window with the output --}}
    <div class="pointer-events-none fixed inset-0 z-[70] hidden items-center justify-center p-4"
         data-docker-dialog role="dialog" aria-modal="true" aria-labelledby="docker-dialog-title">
        <div class="absolute inset-0 bg-canvas/75 backdrop-blur-sm" data-docker-close></div>

        <div class="surface-plain dialog-panel pointer-events-auto relative flex max-h-[34rem] w-full max-w-3xl flex-col p-0">
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text">
                    <x-icon name="terminal" class="size-4"/>
                </span>

                <div class="min-w-0 flex-1">
                    <h2 id="docker-dialog-title" class="truncate text-sm font-semibold text-ink" data-docker-title></h2>
                    <p class="metric mt-0.5 truncate text-[11px] text-dim" data-docker-name></p>
                </div>
            </div>

            <pre class="metric min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-all bg-panel px-5 py-4 text-[11px] leading-relaxed text-muted"
                 data-docker-output></pre>

            <div class="border-t border-line px-5 py-4">
                <button type="button" class="btn btn-ghost w-full text-xs" data-docker-close>
                    {{ __('app.palette.close') }}
                </button>
            </div>
        </div>
    </div>
    <x-mascot pose="whale" class="mascot-at-tail size-20"/>
</x-app-layout>
