<x-app-layout :title="__('app.dev.commands')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.dev.commands') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.dev.commands_hint') }}</p>
            </div>

            <x-dev-tabs active="commands"/>
        </div>

        <div class="field-with-action mt-4">
            <input type="search" class="control text-sm" data-command-filter autocomplete="off"
                   placeholder="{{ __('app.dev.filter_targets') }}" aria-label="{{ __('app.dev.filter_targets') }}">

            <span class="field-action pointer-events-none">
                <x-icon name="search" class="size-4"/>
            </span>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1.4fr_1fr]" data-region="commands">
        <div class="stack">
            @forelse ($projects as $project)
                @php $list = $targets[$project->getKey()]; @endphp

                <x-card class="rise" data-command-project data-name="{{ Str::lower($project->name) }}">
                    <details data-remember="commands.{{ $project->getKey() }}">
                        <summary class="flex cursor-pointer items-center gap-3">
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-dim transition"/>

                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-ink">{{ $project->name }}</span>
                                <span class="metric block truncate text-[11px] text-dim">{{ $project->path }}</span>
                            </span>

                            <span class="pill shrink-0 text-[10px]" data-command-count>
                                {{ trans_choice('app.dev.target_count', count($list)) }}
                            </span>
                        </summary>

                        <div class="mt-4">
                            @if ($missing[$project->getKey()])
                                <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                                    {{ $project->exists() ? __('app.dev.no_makefile') : __('app.dev.missing_path', ['path' => $project->path]) }}
                                </p>
                            @elseif ($list === [])
                                <p class="text-xs text-dim">{{ __('app.dev.no_targets') }}</p>
                            @else
                                <div class="grid gap-1.5 sm:grid-cols-2" data-command-list>
                                    @foreach ($list as $target)
                                        <button type="button" class="row flex w-full items-start gap-2.5 px-3 py-2 text-left"
                                                data-run="{{ route('commands.run', $project) }}" data-target="{{ $target['name'] }}"
                                                data-search="{{ Str::lower($target['name'].' '.($target['description'] ?? '')) }}">
                                            <x-icon name="play" class="mt-0.5 size-3.5 shrink-0 text-accent-text"/>
                                            <span class="min-w-0 flex-1">
                                                <span class="metric block truncate text-xs font-semibold text-ink">make {{ $target['name'] }}</span>
                                                @if ($target['description'])
                                                    <span class="mt-0.5 block truncate text-[11px] text-dim">{{ $target['description'] }}</span>
                                                @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>

                                <p class="mt-3 hidden text-center text-xs text-faint" data-command-empty>
                                    {{ __('app.dev.no_match') }}
                                </p>
                            @endif
                        </div>
                    </details>
                </x-card>
            @empty
                <x-card class="rise">
                    <p class="text-sm text-dim">
                        {{ __('app.dev.no_projects') }}
                        <a href="{{ route('projects') }}" class="text-accent-text hover:underline">{{ __('app.dev.projects') }}</a>
                    </p>
                </x-card>
            @endforelse
        </div>

        <x-card class="rise self-start">
            <h2 class="heading">{{ __('app.run.recent') }}</h2>

            <div class="mt-4 space-y-1.5">
                @forelse ($runs as $run)
                    <button type="button" class="row flex w-full items-center gap-3 px-3 py-2 text-left"
                            data-open-run="{{ route('commands.show', $run) }}">
                        <span class="min-w-0 flex-1">
                            <span class="metric block truncate text-xs text-ink">{{ $run->command() }}</span>
                            <span class="block truncate text-[11px] text-dim">{{ $run->project->name }} · {{ $run->started_at->format('H:i') }}</span>
                        </span>

                        <span class="pill shrink-0 text-[10px] {{ $run->status->classes() }}">{{ $run->status->label() }}</span>
                    </button>
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                        {{ __('app.run.none') }}
                    </p>
                @endforelse
            </div>
        </x-card>
    </div>

    {{-- one window for every run: output, state, and a way to stop it --}}
    <div class="pointer-events-none fixed inset-0 z-[70] hidden items-center justify-center p-4"
         data-run-dialog role="dialog" aria-modal="true" aria-labelledby="run-dialog-title">
        <div class="absolute inset-0 bg-canvas/75 backdrop-blur-sm" data-run-close></div>

        <div class="surface-plain dialog-panel pointer-events-auto relative flex max-h-[34rem] w-full max-w-3xl flex-col p-0">
            <div class="flex flex-wrap items-center gap-3 border-b border-line px-5 py-4">
                <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text">
                    <x-icon name="terminal" class="size-4"/>
                </span>

                <div class="min-w-0 flex-1">
                    <h2 id="run-dialog-title" class="metric truncate text-sm font-semibold text-ink" data-run-command>make</h2>
                    <p class="mt-0.5 truncate text-[11px] text-dim" data-run-project></p>
                </div>

                <span class="pill shrink-0 text-[10px]" data-run-status></span>
            </div>

            <pre class="metric min-h-0 flex-1 overflow-auto whitespace-pre-wrap break-all bg-panel px-5 py-4 text-[11px] leading-relaxed text-muted"
                 data-run-output></pre>

            {{-- a target that asks something can be answered right here --}}
            <form class="hidden border-t border-line px-5 py-3" data-run-input-form>
                <div class="field-with-action">
                    <input type="text" class="control metric text-xs" maxlength="500" autocomplete="off"
                           data-run-input placeholder="{{ __('app.run.input_placeholder') }}"
                           aria-label="{{ __('app.run.input_placeholder') }}">

                    <button type="submit" class="field-action" aria-label="{{ __('app.run.send') }}" title="{{ __('app.run.send') }}">
                        <x-icon name="send" class="size-4"/>
                    </button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                <button type="button" class="btn btn-danger flex-1 text-xs hidden" data-run-stop>
                    <x-icon name="stop" class="size-4"/>
                    {{ __('app.run.stop') }}
                </button>

                <button type="button" class="btn btn-ghost flex-1 text-xs" data-run-close>
                    {{ __('app.palette.close') }}
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
