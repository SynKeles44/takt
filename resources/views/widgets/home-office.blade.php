<x-card data-region="home-office">
    <div class="flex items-center justify-between gap-2">
        <h2 class="heading">{{ __('app.widget.home_office.label') }}</h2>
        <span class="pill border-rest/30 bg-rest/10 text-rest-text shrink-0">
            <x-icon name="home" class="size-3"/>
            {{ __('app.absence.marker') }}
        </span>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-2">
        <div class="tile px-3 py-3 text-center">
            <p class="text-[11px] text-faint">{{ __('app.widget.home_office.this_week') }}</p>
            <p @class([
                    'metric mt-0.5 text-2xl font-bold',
                    'text-work-text' => $summary['target'] > 0 && $thisWeek >= $summary['target'],
                    'text-ink' => ! ($summary['target'] > 0 && $thisWeek >= $summary['target']),
                ])>
                {{ $thisWeek }}@if ($summary['target'] > 0)<span class="text-base font-semibold text-dim">/{{ $summary['target'] }}</span>@endif
            </p>
        </div>

        <div class="tile px-3 py-3 text-center">
            <p class="text-[11px] text-faint">{{ __('app.absence.home_office_per_week') }}</p>
            <p @class([
                    'metric mt-0.5 text-2xl font-bold',
                    'text-danger-text' => $summary['target'] > 0 && $summary['per_week'] + 0.05 < $summary['target'],
                    'text-work-text' => ! ($summary['target'] > 0 && $summary['per_week'] + 0.05 < $summary['target']),
                ])>{{ rtrim(rtrim(number_format($summary['per_week'], 1, ',', ''), '0'), ',') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('dashboard.home-office') }}" class="mt-3 segmented" data-live>
        @csrf
        @foreach ($windows as $option)
            <button type="submit" name="window" value="{{ $option }}"
                    @class(['segment', 'segment-active' => $option === $window])>
                {{ __('app.widget.home_office.window_'.$option) }}
            </button>
        @endforeach
    </form>

    <p class="mt-3 text-[11px] leading-snug text-faint">
        {{ __('app.widget.home_office.footer', ['days' => $summary['days_window'], 'window' => $summary['window'], 'year' => $summary['days_year']]) }}
    </p>
</x-card>
