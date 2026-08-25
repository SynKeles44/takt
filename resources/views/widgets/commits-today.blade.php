<x-card>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.dev.commits') }}</h2>
        <div class="flex items-center gap-2">
            <span class="pill">{{ $count }}</span>
            <a href="{{ route('dev') }}" class="pill hover:text-ink">{{ __('app.nav.dev') }}</a>
        </div>
    </div>

    @if ($groups->isEmpty())
        <p class="mt-4 text-sm text-dim">
            {{ __('app.dev.no_projects') }}
            <a href="{{ route('projects') }}" class="text-accent-text hover:underline">{{ __('app.dev.projects') }}</a>
        </p>
    @else
        <div class="mt-4 space-y-3">
            @foreach ($groups as $group)
                @continue ($group['commits'] === [] && $group['error'] === null)

                <div>
                    <div class="flex items-center justify-between gap-3 px-1">
                        <h3 class="heading">{{ $group['project']->name }}</h3>
                        <span class="metric text-xs text-dim">{{ count($group['commits']) }}</span>
                    </div>

                    @if ($group['error'])
                        <p class="mt-2 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                            {{ $group['error'] }}
                        </p>
                    @else
                        <ul class="mt-2 space-y-1.5">
                            @foreach (array_slice($group['commits'], 0, 5) as $commit)
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

            @if ($count === 0)
                <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                    {{ __('app.dev.no_commits') }}
                </p>
            @endif
        </div>
    @endif
</x-card>
