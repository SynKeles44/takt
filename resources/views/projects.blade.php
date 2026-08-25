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
                    <div class="flex items-center justify-between gap-2">
                        <label for="path" class="label">{{ __('app.dev.path') }}</label>
                        <button type="button" class="btn btn-ghost native-only px-2 py-1 text-[11px]" data-pick-folder>
                            <x-icon name="folder" class="size-3.5"/>
                            {{ __('app.dev.pick_folder') }}
                        </button>
                    </div>
                    <input id="path" type="text" name="path" value="{{ old('path') }}" class="control metric text-xs"
                           maxlength="400" placeholder="~/PhpstormProjects/…" required data-scan-path>
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
</x-app-layout>
