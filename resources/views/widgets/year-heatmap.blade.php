<x-card>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.year_heatmap.label') }}</h2>
        <a href="{{ route('insights', ['zeitraum' => 'jahr']) }}" class="pill hover:text-ink">{{ __('app.nav.insights') }}</a>
    </div>

    <div class="mt-4">
        <x-heatmap :heatmap="$year['heatmap']" :daily-target="$dailyTarget"/>
    </div>
</x-card>
