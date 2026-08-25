<x-app-layout :title="__('app.dev.projects')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.dev.projects') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.dev.projects_hint') }}</p>
            </div>

            <x-dev-tabs active="projects"/>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1.3fr_1fr]">
        <x-card class="rise">
            <h2 class="heading">{{ __('app.dev.registered') }}</h2>

            @forelse ($projects as $project)
                @php $state = $states[$project->getKey()]; @endphp

                <details class="mt-3">
                    <summary class="row flex cursor-pointer items-center gap-3 px-3 py-2">
                        <span @class(['dot size-2 shrink-0', 'bg-work' => $state['running'] || $state['port_open'], 'bg-line-strong' => ! $state['running'] && ! $state['port_open']])></span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-ink">{{ $project->name }}</span>
                            <span class="metric block truncate text-[11px] text-dim">{{ $project->path }}</span>
                        </span>

                        @if ($project->port)<span class="pill metric shrink-0 text-[10px]">:{{ $project->port }}</span>@endif
                        @unless ($project->exists())<span class="pill shrink-0 border-danger/40 bg-danger/10 text-[10px] text-danger-text">{{ __('app.dev.missing') }}</span>@endunless
                    </summary>

                    <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-3 border-t border-line p-4" data-live>
                        @csrf
                        @method('PUT')

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <label class="label">{{ __('app.dev.name') }}</label>
                                <input type="text" name="name" value="{{ $project->name }}" class="control" maxlength="80" required>
                            </div>
                            <div>
                                <label class="label">{{ __('app.dev.repository') }}</label>
                                <input type="text" name="repository" value="{{ $project->repository }}" class="control" maxlength="200" placeholder="owner/repo">
                            </div>
                        </div>

                        <div>
                            <label class="label">{{ __('app.dev.path') }}</label>
                            <input type="text" name="path" value="{{ $project->path }}" class="control metric text-xs" maxlength="400" required>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6.5rem]">
                            <div class="min-w-0">
                                <label class="label">{{ __('app.dev.command') }}</label>
                                <input type="text" name="start_command" value="{{ $project->start_command }}" class="control metric text-xs" maxlength="400" placeholder="make start">
                            </div>
                            <div class="min-w-0">
                                <label class="label">{{ __('app.dev.port') }}</label>
                                <input type="number" name="port" value="{{ $project->port }}" class="control metric" min="1" max="65535" placeholder="—">
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary text-xs">
                                <x-icon name="check" class="size-4"/>
                                {{ __('app.settings.save') }}
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('projects.destroy', $project) }}" class="px-4 pb-4"
                          data-confirm="{{ __('app.dev.confirm_delete_project') }}" data-live>
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger w-full text-xs">
                            <x-icon name="trash" class="size-3.5"/>
                            {{ __('app.form.delete') }}
                        </button>
                    </form>
                </details>
            @empty
                <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
                    {{ __('app.dev.no_projects_short') }}
                </p>
            @endforelse
        </x-card>

        <x-card class="rise self-start">
            <h2 class="heading">{{ __('app.dev.new_project') }}</h2>

            <form method="POST" action="{{ route('projects.store') }}" class="mt-4 space-y-3" data-live
                  data-scan-form data-scan-url="{{ route('projects.scan') }}"
                  data-scanned="{{ __('app.dev.scanned', ['name' => ':name']) }}" data-scan-empty="{{ __('app.dev.scan_empty') }}">
                @csrf

                <div>
                    <label for="name" class="label">{{ __('app.dev.name') }}</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="control" maxlength="80" required data-refocus>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="path" class="label">{{ __('app.dev.path') }}</label>

                    <div class="field-with-action">
                        <input id="path" type="text" name="path" value="{{ old('path') }}" class="control metric text-xs"
                               maxlength="400" placeholder="~/PhpstormProjects/…" required data-scan-path>

                        <button type="button" class="field-action" data-pick-folder
                                aria-label="{{ __('app.dev.pick_folder') }}" title="{{ __('app.dev.pick_folder') }}">
                            <x-icon name="folder" class="size-4"/>
                        </button>
                    </div>

                    <p class="mt-1 text-[11px] text-dim">{{ __('app.dev.path_hint') }}</p>
                    @error('path') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="repository" class="label">{{ __('app.dev.repository') }}</label>
                    <input id="repository" type="text" name="repository" value="{{ old('repository') }}" class="control text-xs" maxlength="200" placeholder="owner/repo">
                    <p class="mt-1 text-[11px] text-dim">{{ __('app.dev.repository_hint') }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6.5rem]">
                    <div class="min-w-0">
                        <label for="start_command" class="label">{{ __('app.dev.command') }}</label>
                        <input id="start_command" type="text" name="start_command" value="{{ old('start_command', 'make start') }}" class="control metric text-xs" maxlength="400" placeholder="make start">
                    </div>
                    <div class="min-w-0">
                        <label for="port" class="label">{{ __('app.dev.port') }}</label>
                        <input id="port" type="number" name="port" value="{{ old('port') }}" class="control metric" min="1" max="65535" placeholder="—">
                    </div>
                </div>

                <p class="text-[11px] leading-relaxed text-faint">{{ __('app.dev.command_hint') }}</p>
                <p class="text-[11px] leading-relaxed text-faint">{{ __('app.dev.port_hint') }}</p>

                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="plus" class="size-4"/>
                    {{ __('app.dev.add') }}
                </button>
            </form>
        </x-card>
    </div>

    {{-- Takt's own folder picker: a browser hands out no absolute path, the local server does --}}
    <div class="pointer-events-none fixed inset-0 z-[70] hidden items-center justify-center p-4"
         data-folder-dialog data-folders-url="{{ route('projects.folders') }}"
         data-empty-label="{{ __('app.dev.folder_empty') }}" data-failed-label="{{ __('app.dev.folder_failed') }}"
         role="dialog" aria-modal="true" aria-labelledby="folder-dialog-title">
        <div class="absolute inset-0 bg-canvas/75 backdrop-blur-sm" data-folder-cancel></div>

        <div class="surface-plain dialog-panel pointer-events-auto relative flex max-h-[32rem] w-full max-w-lg flex-col p-0">
            <div class="border-b border-line px-5 py-4">
                <div class="flex items-center gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text">
                        <x-icon name="folder" class="size-4"/>
                    </span>
                    <h2 id="folder-dialog-title" class="text-sm font-semibold text-ink">{{ __('app.dev.pick_folder') }}</h2>
                </div>

                {{-- the path as crumbs: one click goes back up as far as you like --}}
                <nav class="folder-crumbs mt-3 flex flex-wrap items-center gap-x-1 gap-y-1" data-folder-crumbs
                     aria-label="{{ __('app.dev.path') }}"></nav>
            </div>

            <div class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 py-3" data-folder-list></div>

            <div class="flex gap-2 border-t border-line px-5 py-4">
                <button type="button" class="btn btn-ghost flex-1" data-folder-cancel>
                    {{ __('app.dialog.cancel') }}
                </button>
                <button type="button" class="btn btn-primary flex-1" data-folder-choose>
                    <x-icon name="check" class="size-4"/>
                    {{ __('app.dev.folder_choose') }}
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
