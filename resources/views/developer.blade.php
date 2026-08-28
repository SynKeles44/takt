@use('App\Support\Duration')

<x-app-layout :title="__('app.nav.dev')" :wide="true">
    <div data-region="dev-head">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ $day->isoFormat('dddd, D. MMMM YYYY') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ trans_choice('app.dev.commit_count', $commitCount) }}</p>
            </div>

            {{-- the day navigation sits left of the tabs, so the tabs keep their place on every page --}}
            <div class="flex flex-wrap items-center justify-end gap-2">
                <div class="tile flex items-center gap-1 p-1">
                    <a href="{{ route('dev', ['tag' => $previousDay]) }}" class="icon-action"
                       data-partial="dev-head dev-commits" aria-label="{{ __('app.calendar.previous') }}">
                        <x-icon name="chevron-left" class="size-4"/>
                    </a>
                    <a href="{{ route('dev') }}" data-partial="dev-head dev-commits"
                       @class(['rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition', 'text-dim' => $isToday, 'text-muted hover:text-ink' => ! $isToday])>
                        {{ __('app.insights.now') }}
                    </a>
                    <a href="{{ route('dev', ['tag' => $nextDay]) }}" class="icon-action"
                       data-partial="dev-head dev-commits" aria-label="{{ __('app.calendar.next') }}">
                        <x-icon name="chevron-right" class="size-4"/>
                    </a>
                </div>

                <x-dev-tabs active="dev"/>
            </div>
        </div>
    </x-card>
    </div>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1fr_1.6fr]">
        {{-- left: the short lists, right: the pull requests, which need the room --}}
        <div class="stack">
        <x-card class="rise" data-region="dev-commits">
            <h2 class="heading">{{ __('app.dev.commits') }}</h2>

            @if ($groups->isEmpty())
                <p class="mt-4 text-sm text-dim">
                    {{ __('app.dev.no_projects') }}
                    <a href="{{ route('projects') }}" class="text-accent-text hover:underline">{{ __('app.dev.projects') }}</a>
                </p>
            @else
                <div class="mt-4 space-y-2">
                    @foreach ($groups as $group)
                        @php $count = count($group['commits']); @endphp

                        {{-- collapsible per project, and it remembers what you closed --}}
                        <details class="tile overflow-hidden" data-remember="commits.{{ $group['project']->getKey() }}">
                            <summary class="flex cursor-pointer items-center gap-3 px-3 py-2">
                                <x-icon name="chevron-right" class="size-3.5 shrink-0 text-dim transition"/>
                                <span class="min-w-0 flex-1 truncate text-xs font-semibold text-ink">{{ $group['project']->name }}</span>

                                @if ($group['error'])
                                    <span class="pill shrink-0 border-rest/40 bg-rest/10 text-[10px] text-rest-text">!</span>
                                @else
                                    <span @class(['pill shrink-0 text-[10px]', 'text-dim' => $count === 0])>{{ $count }}</span>
                                @endif
                            </summary>

                            <div class="border-t border-line p-2">
                                @if ($group['error'])
                                    <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                                        {{ $group['error'] }}
                                    </p>
                                @elseif ($count === 0)
                                    <p class="px-2 py-1 text-xs text-dim">{{ __('app.dev.no_commits') }}</p>
                                @else
                                    <ul class="space-y-1.5">
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
                        </details>
                    @endforeach
                </div>
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

        {{--
            The reviews come from GitHub and cost over a second when nothing is cached, so
            the page renders what the cache has and fetches the rest once it stands.
        --}}
        <div data-reviews-slot data-reviews-url="{{ route('dev.reviews.sections') }}">
            @if ($reviews !== null)
                @include('partials.reviews', [
                    'reviews' => $reviews,
                    'reviewsConfigured' => $reviewsConfigured,
                    'projects' => $projects,
                    'byProject' => $byProject,
                    'unassigned' => $unassigned,
                    'clipboard' => $clipboard,
                ])
            @else
                <x-card>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.dev.reviews') }}</h2>
                        <span class="pill text-[10px]">{{ __('app.dev.reviews_loading') }}</span>
                    </div>

                    <div class="mt-4 space-y-1.5">
                        @foreach (range(1, 3) as $ignored)
                            <div class="row h-11 animate-pulse px-3 py-2"></div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
