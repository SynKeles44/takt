<x-card>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="heading">{{ $title }}</h2>

        @if ($configured)
            <div class="flex items-center gap-2">
                <span class="pill">{{ count($reviews[$bucket]) }}</span>
                <form method="POST" action="{{ route('dev.reviews') }}" data-live>
                    @csrf
                    <button type="submit" class="icon-action" aria-label="{{ __('app.dev.refresh') }}" title="{{ __('app.dev.refresh') }}">
                        <x-icon name="repeat" class="size-4"/>
                    </button>
                </form>
            </div>
        @endif
    </div>

    @if (! $configured)
        <p class="mt-4 text-sm text-dim">{{ __('app.dev.no_token') }}</p>
        <a href="{{ route('settings') }}" class="btn btn-ghost mt-3 w-full text-xs">
            <x-icon name="gear" class="size-3.5"/>
            {{ __('app.nav.settings') }}
        </a>
    @elseif ($reviews['error'])
        <p class="mt-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger-text">
            {{ $reviews['error'] }}
        </p>
    @elseif ($reviews[$bucket] === [])
        <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
            {{ __('app.dev.reviews_empty') }}
        </p>
    @else
        <ul class="mt-4 space-y-1.5">
            @foreach ($reviews[$bucket] as $pull)
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

        @if ($reviews['fetched_at'])
            <p class="mt-3 text-[11px] text-dim">{{ __('app.dev.fetched', ['time' => $reviews['fetched_at']->format('H:i')]) }}</p>
        @endif
    @endif
</x-card>
