{{-- Both review sections, on their own so the page can pull them in after it stands. --}}
<div class="stack" data-review-sections>
    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="heading">{{ __('app.dev.reviews_incoming') }}</h2>

            @if ($reviewsConfigured)
                <div class="flex items-center gap-2">
                    <span class="pill">{{ count($reviews['incoming']) }}</span>
                    <form method="POST" action="{{ route('dev.reviews') }}" data-live>
                        @csrf
                        <button type="submit" class="icon-action" aria-label="{{ __('app.dev.refresh') }}" title="{{ __('app.dev.refresh') }}">
                            <x-icon name="repeat" class="size-4"/>
                        </button>
                    </form>
                </div>
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
            <x-pull-list :pulls="$reviews['incoming']"/>

            @php $blocked = collect($reviews['repositories'])->filter(fn (array $repo): bool => $repo['status'] !== 'ok'); @endphp

            @if ($blocked->isNotEmpty())
                <div class="mt-3 space-y-1.5">
                    @foreach ($blocked as $slug => $repo)
                        <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-[11px] leading-relaxed text-rest-text">
                            <span class="metric">{{ $slug }}</span> — {{ $repo['message'] }}
                        </p>
                    @endforeach
                </div>
            @endif

            @if ($reviews['fetched_at'])
                <p class="mt-3 text-[11px] text-dim">{{ __('app.dev.fetched', ['time' => $reviews['fetched_at']->format('H:i')]) }}</p>
            @endif
        @endif
    </x-card>

    @if ($reviewsConfigured && ! $reviews['error'])
        <x-card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="heading">{{ __('app.dev.my_pulls') }}</h2>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($reviews['mine'] !== [])
                        <label class="pill cursor-pointer gap-1.5 text-[10px]">
                            <input type="checkbox" class="size-3 accent-[var(--color-accent)]" data-copy-titles>
                            {{ __('app.dev.with_titles') }}
                        </label>
                    @endif

                    @if (($clipboard['all'] ?? '') !== '')
                        <button type="button" class="btn btn-ghost text-xs" data-copy="{{ $clipboard['all'] }}"
                                data-copy-scope="all" data-copy-empty="{{ __('app.dev.nothing_picked') }}"
                                data-copy-label="{{ __('app.dev.all_copied') }}" title="{{ __('app.dev.copy_all_hint') }}">
                            <x-icon name="clipboard" class="size-3.5"/>
                            {{ __('app.dev.copy_all') }}
                        </button>
                    @endif

                    <span class="pill">{{ count($reviews['mine']) }}</span>
                </div>
            </div>

            @if ($reviews['mine'] === [])
                <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                    {{ __('app.dev.reviews_empty') }}
                </p>
            @else
                {{-- one column: two of them halve the width, and a conventional-commit title
                     does not survive that --}}
                <div class="mt-4 space-y-2">
                    @foreach ($projects as $project)
                        @php
                            $slug = $project->slug();
                            $pulls = $byProject[$project->getKey()];
                            $repo = $slug === null ? null : ($reviews['repositories'][$slug] ?? null);
                        @endphp
                        @continue ($slug === null)

                        <details class="tile overflow-hidden" data-remember="pulls.{{ $project->getKey() }}"
                                 data-pull-group data-copy-heading="{{ $project->name }}">
                            <summary class="flex cursor-pointer items-center gap-3 px-3 py-2">
                                <x-icon name="chevron-right" class="size-3.5 shrink-0 text-dim transition"/>
                                <span class="min-w-0 flex-1 truncate text-xs font-semibold text-ink">{{ $project->name }}</span>

                                @if (($repo['status'] ?? 'ok') !== 'ok')
                                    <span class="pill shrink-0 border-rest/40 bg-rest/10 text-[10px] text-rest-text">!</span>
                                @else
                                    @if ($pulls !== [])
                                        <button type="button" class="icon-action shrink-0"
                                                data-copy="{{ $clipboard['projects'][$project->getKey()] ?? '' }}"
                                                data-copy-scope="group" data-copy-empty="{{ __('app.dev.nothing_picked') }}"
                                                data-copy-label="{{ __('app.dev.project_copied', ['project' => $project->name]) }}"
                                                title="{{ __('app.dev.copy_project') }}" aria-label="{{ __('app.dev.copy_project') }}">
                                            <x-icon name="clipboard" class="size-3.5"/>
                                        </button>
                                    @endif

                                    <span @class(['pill shrink-0 text-[10px]', 'text-dim' => $pulls === []])>{{ count($pulls) }}</span>
                                @endif
                            </summary>

                            <div class="border-t border-line p-2">
                                @if (($repo['status'] ?? 'ok') !== 'ok')
                                    <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-[11px] leading-relaxed text-rest-text">
                                        {{ $repo['message'] }}
                                    </p>
                                @else
                                    <x-pull-list :pulls="$pulls" :compact="true" :selectable="true"/>
                                @endif
                            </div>
                        </details>
                    @endforeach

                    @if ($unassigned !== [])
                        <details class="tile overflow-hidden" data-pull-group
                                 data-copy-heading="{{ __('app.dev.other_repositories') }}">
                            <summary class="flex cursor-pointer items-center gap-3 px-3 py-2">
                                <x-icon name="chevron-right" class="size-3.5 shrink-0 text-dim transition"/>
                                <span class="min-w-0 flex-1 truncate text-xs font-semibold text-muted">{{ __('app.dev.other_repositories') }}</span>

                                @if (($clipboard['unassigned'] ?? '') !== '')
                                    <button type="button" class="icon-action shrink-0" data-copy="{{ $clipboard['unassigned'] }}"
                                            data-copy-scope="group" data-copy-empty="{{ __('app.dev.nothing_picked') }}"
                                            data-copy-label="{{ __('app.dev.all_copied') }}"
                                            title="{{ __('app.dev.copy_project') }}" aria-label="{{ __('app.dev.copy_project') }}">
                                        <x-icon name="clipboard" class="size-3.5"/>
                                    </button>
                                @endif

                                <span class="pill shrink-0 text-[10px]">{{ count($unassigned) }}</span>
                            </summary>

                            <div class="border-t border-line p-2">
                                <x-pull-list :pulls="$unassigned" :compact="true" :selectable="true"/>
                            </div>
                        </details>
                    @endif
                </div>
            @endif
        </x-card>
    @endif
</div>
