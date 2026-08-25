@use('App\Support\Duration')

@props(['heatmap', 'dailyTarget'])

<div>
    <div class="flex items-center justify-end gap-1.5 text-[11px] text-dim">
        <span>{{ __('app.insights.less') }}</span>
        @foreach (['bg-hover', 'bg-work/25', 'bg-work/45', 'bg-work/70', 'bg-work'] as $swatch)
            <span class="size-3 rounded-[3px] {{ $swatch }}"></span>
        @endforeach
        <span>{{ __('app.insights.more_legend') }}</span>
    </div>

    <div class="mt-3 overflow-x-auto pb-1">
        <div class="flex min-w-max gap-[3px]">
            @foreach ($heatmap as $week)
                <div class="flex flex-col gap-[3px]">
                    @foreach ($week as $day)
                        @php
                            $ratio = $dailyTarget > 0 ? $day['work'] / $dailyTarget : 0;
                            $level = match (true) {
                                $day['work'] === 0 => 'bg-hover',
                                $ratio < 0.5 => 'bg-work/25',
                                $ratio < 0.85 => 'bg-work/45',
                                $ratio < 1.05 => 'bg-work/70',
                                default => 'bg-work',
                            };
                        @endphp
                        <span @class(['size-3 rounded-[3px]', $level, 'opacity-25' => ! $day['inRange']])
                              title="{{ $day['date']->isoFormat('dd, D. MMM YYYY') }} · {{ $day['work'] > 0 ? Duration::human($day['work']) : '–' }}"></span>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
