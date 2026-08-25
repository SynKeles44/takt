<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.dev.projects') }}</h2>
        <a href="{{ route('projects') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    @if ($projects->isEmpty())
        <p class="mt-4 text-sm text-dim">{{ __('app.dev.no_projects_short') }}</p>
    @else
        <div class="mt-4 space-y-1.5">
            @foreach ($projects as $project)
                @php $state = $states[$project->getKey()]; @endphp
                <div class="row flex items-center gap-3 px-3 py-2">
                    <span @class(['dot size-2 shrink-0', 'bg-work' => $state['running'] || $state['port_open'], 'bg-line-strong' => ! $state['running'] && ! $state['port_open']])></span>

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm text-ink">{{ $project->name }}</span>
                        @if ($project->start_command)
                            <span class="metric block truncate text-[11px] text-dim">{{ $project->start_command }}</span>
                        @endif
                    </span>

                    @if ($project->port && ($state['running'] || $state['port_open']))
                        <a href="http://localhost:{{ $project->port }}" target="_blank" class="icon-action shrink-0"
                           aria-label="{{ __('app.dev.open') }}" title="{{ __('app.dev.open') }}">
                            <x-icon name="external" class="size-4"/>
                        </a>
                    @endif

                    @if ($project->start_command)
                        <form method="POST" action="{{ $state['running'] ? route('projects.stop', $project) : route('projects.start', $project) }}" class="shrink-0" data-live>
                            @csrf
                            <button type="submit" class="icon-action"
                                    aria-label="{{ $state['running'] ? __('app.dev.stop') : __('app.dev.start') }}"
                                    title="{{ $state['running'] ? __('app.dev.stop') : __('app.dev.start') }}">
                                <x-icon :name="$state['running'] ? 'stop' : 'play'" class="size-4"/>
                            </button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-card>
