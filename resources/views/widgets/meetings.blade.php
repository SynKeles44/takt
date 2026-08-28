@use('App\Support\Duration')

<x-card>
    <div class="flex items-center justify-between gap-2">
        <h2 class="heading">{{ __('app.widget.meetings.label') }}</h2>
        @if ($events !== [])
            <span class="pill shrink-0 text-[10px]">{{ Duration::human(array_sum(array_column($events, 'seconds'))) }}</span>
        @endif
    </div>

    @if ($events === [])
        <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
            {{ __('app.widget.meetings.empty') }}
        </p>
    @else
        <ul class="mt-4 space-y-1.5">
            @foreach ($events as $event)
                <li class="row flex items-start gap-3 px-3 py-2">
                    <span class="metric shrink-0 text-xs text-accent-text">{{ $event['from']->format('H:i') }}</span>

                    <span class="min-w-0 flex-1">
                        <span class="line-clamp-2 block text-sm text-ink">{{ $event['title'] }}</span>
                        @if ($event['calendar'] !== null)
                            <span class="block truncate text-[11px] text-dim">{{ $event['calendar'] }}</span>
                        @endif
                    </span>

                    <form method="POST" action="{{ route('entries.store') }}" class="shrink-0" data-live>
                        @csrf
                        <input type="hidden" name="date" value="{{ $event['from']->toDateString() }}">
                        <input type="hidden" name="work_starts_at" value="{{ $event['from']->format('H:i') }}">
                        <input type="hidden" name="work_ends_at" value="{{ $event['to']->format('H:i') }}">
                        <input type="hidden" name="note" value="{{ $event['title'] }}">

                        <button type="submit" class="icon-action" title="{{ __('app.widget.meetings.book') }}"
                                aria-label="{{ __('app.widget.meetings.book') }}">
                            <x-icon name="plus" class="size-3.5"/>
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
