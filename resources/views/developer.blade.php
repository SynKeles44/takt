@use('App\Support\Duration')

<x-app-layout :title="__('app.nav.dev')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ $day->isoFormat('dddd, D. MMMM YYYY') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ trans_choice('app.dev.commit_count', $commitCount) }}</p>
            </div>

            {{-- the day navigation sits left of the tabs, so the tabs keep their place on every page --}}
            <div class="flex flex-wrap items-center justify-end gap-2">
                <div class="tile flex items-center gap-1 p-1">
                    <a href="{{ route('dev', ['tag' => $previousDay]) }}" class="icon-action" aria-label="{{ __('app.calendar.previous') }}">
                        <x-icon name="chevron-left" class="size-4"/>
                    </a>
                    <a href="{{ route('dev') }}"
                       @class(['rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition', 'text-dim' => $isToday, 'text-muted hover:text-ink' => ! $isToday])>
                        {{ __('app.insights.now') }}
                    </a>
                    <a href="{{ route('dev', ['tag' => $nextDay]) }}" class="icon-action" aria-label="{{ __('app.calendar.next') }}">
                        <x-icon name="chevron-right" class="size-4"/>
                    </a>
                </div>

                <x-dev-tabs active="dev"/>
            </div>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1.25fr_1fr]">
        <x-card class="rise">
            <h2 class="heading">{{ __('app.dev.commits') }}</h2>

            @if ($groups->isEmpty())
                <p class="mt-4 text-sm text-dim">
                    {{ __('app.dev.no_projects') }}
                    <a href="{{ route('projects') }}" class="text-accent-text hover:underline">{{ __('app.dev.projects') }}</a>
                </p>
            @else
                <div class="mt-4 space-y-4">
                    @foreach ($groups as $group)
                        <div>
                            <div class="flex items-center justify-between gap-3 px-1">
                                <h3 class="heading">{{ $group['project']->name }}</h3>
                                <span class="metric text-xs text-dim">{{ count($group['commits']) }}</span>
                            </div>

                            @if ($group['error'])
                                <p class="mt-2 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                                    {{ $group['error'] }}
                                </p>
                            @elseif ($group['commits'] === [])
                                <p class="mt-2 px-1 text-xs text-dim">{{ __('app.dev.no_commits') }}</p>
                            @else
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($group['commits'] as $commit)
                                        <li class="row flex items-start gap-3 px-3 py-2">
                                            <span class="metric shrink-0 text-xs text-accent-text">{{ $commit['short'] }}</span>
                                            <span class="min-w-0 flex-1 text-sm text-ink">{{ $commit['subject'] }}</span>
                                            <span class="metric shrink-0 text-[11px] text-dim">{{ $commit['at']->format('H:i') }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        <div class="stack">
            <x-card class="rise">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="heading">{{ __('app.dev.reviews') }}</h2>

                    @if ($reviewsConfigured)
                        <form method="POST" action="{{ route('dev.reviews') }}" data-live>
                            @csrf
                            <button type="submit" class="icon-action" aria-label="{{ __('app.dev.refresh') }}" title="{{ __('app.dev.refresh') }}">
                                <x-icon name="repeat" class="size-4"/>
                            </button>
                        </form>
                    @endif
                </div>

                @if (! $reviewsConfigured)
                    <p class="mt-4 text-sm text-dim">{{ __('app.dev.no_token') }}</p>
                    <a href="{{ route('settings') }}" class="btn btn-ghost mt-3 w-full text-xs">
                        <x-icon name="gear" class="size-3.5"/>
                        {{ __('app.nav.settings') }}
                    </a>
                @elseif ($reviews['error'])
                    <p class="mt-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger-text">
                        {{ $reviews['error'] }}
                    </p>
                @else
                    @foreach ([['incoming', __('app.dev.reviews_incoming')], ['mine', __('app.dev.reviews_mine')]] as [$key, $label])
                        <div class="mt-4">
                            <div class="flex items-center justify-between gap-2 px-1">
                                <h3 class="heading">{{ $label }}</h3>
                                <span class="metric text-xs text-dim">{{ count($reviews[$key]) }}</span>
                            </div>

                            @if ($reviews[$key] === [])
                                <p class="mt-2 px-1 text-xs text-dim">{{ __('app.dev.reviews_empty') }}</p>
                            @else
                                <ul class="mt-2 space-y-1.5">
                                    @foreach ($reviews[$key] as $pull)
                                        <li class="row flex items-start gap-3 px-3 py-2">
                                            <span class="min-w-0 flex-1">
                                                <a href="{{ $pull['url'] }}" target="_blank" class="block truncate text-sm text-ink hover:text-accent-text">
                                                    {{ $pull['title'] }}
                                                </a>
                                                <span class="block truncate text-[11px] text-dim">
                                                    {{ $pull['repository'] }} #{{ $pull['number'] }}
                                                    @if ($pull['draft']) · {{ __('app.dev.draft') }} @endif
                                                </span>
                                            </span>

                                            <span @class([
                                                    'pill shrink-0 text-[10px]',
                                                    'border-danger/40 bg-danger/10 text-danger-text' => $pull['updated_at']->diffInHours() >= 24,
                                                ])>
                                                {{ $pull['updated_at']->diffForHumans(['short' => true, 'parts' => 1]) }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach

                    @if ($reviews['fetched_at'])
                        <p class="mt-3 text-[11px] text-dim">{{ __('app.dev.fetched', ['time' => $reviews['fetched_at']->format('H:i')]) }}</p>
                    @endif
                @endif
            </x-card>

            <x-card class="rise">
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
                                    @if ($project->port)
                                        <span class="metric block text-[11px] text-dim">:{{ $project->port }}</span>
                                    @endif
                                </span>

                                @if ($project->start_command)
                                    <form method="POST" action="{{ $state['running'] ? route('projects.stop', $project) : route('projects.start', $project) }}" class="shrink-0" data-live>
                                        @csrf
                                        <button type="submit" class="icon-action" aria-label="{{ $state['running'] ? __('app.dev.stop') : __('app.dev.start') }}"
                                                title="{{ $state['running'] ? __('app.dev.stop') : __('app.dev.start') }}">
                                            <x-icon :name="$state['running'] ? 'stop' : 'play'" class="size-4"/>
                                        </button>
                                    </form>
                                @endif

                                @if ($project->port && $state['port_open'])
                                    <a href="http://localhost:{{ $project->port }}" target="_blank" class="icon-action shrink-0" aria-label="{{ __('app.dev.open') }}" title="{{ __('app.dev.open') }}">
                                        <x-icon name="chevron-right" class="size-4"/>
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            @if ($snippets->isNotEmpty())
                <x-card class="rise">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.dev.snippets') }}</h2>
                        <a href="{{ route('snippets') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
                    </div>

                    <div class="mt-4 space-y-1.5">
                        @foreach ($snippets as $snippet)
                            <button type="button" class="row flex w-full items-center gap-3 px-3 py-2 text-left"
                                    data-copy="{{ $snippet->body }}" data-copy-ping="{{ route('snippets.used', $snippet) }}"
                                    title="{{ __('app.dev.copy') }}">
                                <x-icon name="paperclip" class="size-4 shrink-0 text-muted"/>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-ink">{{ $snippet->title }}</span>
                                    <span class="metric block truncate text-[11px] text-dim">{{ $snippet->body }}</span>
                                </span>
                                @if ($snippet->label)
                                    <span class="pill shrink-0 text-[10px]">{{ $snippet->label }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
