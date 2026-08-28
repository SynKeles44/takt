@use('App\Support\Duration')

<x-card>
    <div class="flex items-center justify-between gap-2">
        <h2 class="heading">{{ __('app.widget.activity.label') }}</h2>

        @if ($enabled && $apps->isNotEmpty())
            <span class="pill shrink-0 text-[10px]">{{ Duration::human((int) $apps->sum('seconds')) }}</span>
        @endif
    </div>

    @if (! $enabled)
        <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
            {{ __('app.trail.empty') }}
        </p>
        <a href="{{ route('settings') }}" class="btn btn-ghost mt-3 w-full text-xs">
            <x-icon name="gear" class="size-3.5"/>
            {{ __('app.trail.title') }}
        </a>
    @else
        @if ($proposals->isNotEmpty())
            <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.12em] text-faint">{{ __('app.trail.proposals') }}</p>

            <div class="mt-2 space-y-1.5">
                @foreach ($proposals as $proposal)
                    <div class="row flex items-start gap-3 px-3 py-2">
                        <span class="metric shrink-0 text-xs text-accent-text">{{ $proposal['from']->format('H:i') }}</span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink">{{ $proposal['app'] }}</span>
                            @if ($proposal['title'] !== null)
                                <span class="block truncate text-[11px] text-dim">{{ $proposal['title'] }}</span>
                            @endif
                        </span>

                        <span class="pill shrink-0 text-[10px]">{{ Duration::human($proposal['seconds']) }}</span>

                        <form method="POST" action="{{ route('entries.store') }}" class="shrink-0" data-live>
                            @csrf
                            <input type="hidden" name="date" value="{{ $proposal['from']->toDateString() }}">
                            <input type="hidden" name="work_starts_at" value="{{ $proposal['from']->format('H:i') }}">
                            <input type="hidden" name="work_ends_at" value="{{ $proposal['to']->format('H:i') }}">
                            <input type="hidden" name="note" value="{{ $proposal['title'] ?? $proposal['app'] }}">

                            <button type="submit" class="icon-action" title="{{ __('app.trail.book') }}"
                                    aria-label="{{ __('app.trail.book') }}">
                                <x-icon name="plus" class="size-3.5"/>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($apps->isEmpty())
            <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.trail.empty') }}
            </p>
        @else
            @php $peak = max(1, (int) $apps->max('seconds')); @endphp

            <div class="mt-4 space-y-2">
                @foreach ($apps->take(8) as $app)
                    <div>
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="min-w-0 truncate text-sm text-ink">{{ $app['app'] }}</span>
                            <span class="metric shrink-0 text-[11px] text-dim">{{ Duration::human($app['seconds']) }}</span>
                        </div>

                        <div class="mt-1 h-1.5 overflow-hidden rounded-[var(--radius-bar)] bg-line">
                            <div class="h-full rounded-[var(--radius-bar)] bg-gradient-to-r from-accent to-accent-2"
                                 style="width: {{ round($app['seconds'] / $peak * 100, 1) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-card>
