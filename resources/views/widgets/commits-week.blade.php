<x-card>
    <h2 class="heading">{{ __('app.widget.commits_week.label') }}</h2>

    @php
        $peak = max(1, $days->max());
        $total = $days->sum();
    @endphp

    <p class="metric mt-3 text-2xl font-bold {{ $total > 0 ? 'text-accent-text' : 'text-dim' }}">{{ $total }}</p>

    <div class="mt-3 flex h-16 items-end gap-1.5">
        @foreach ($days as $date => $count)
            <div class="flex h-full flex-1 flex-col justify-end" title="{{ \Illuminate\Support\Carbon::parse($date)->isoFormat('dd, D. MMM') }}: {{ $count }}">
                <div @class(['bar w-full', 'bg-gradient-to-t from-accent to-accent-2' => $count > 0, 'bg-hover' => $count === 0])
                     style="height: {{ $count > 0 ? max(8, round($count / $peak * 100)) : 4 }}%"></div>
            </div>
        @endforeach
    </div>

    <div class="mt-2 flex gap-1.5 text-[10px] text-dim">
        @foreach ($days as $date => $count)
            <span class="flex-1 text-center uppercase">{{ \Illuminate\Support\Carbon::parse($date)->isoFormat('dd') }}</span>
        @endforeach
    </div>
</x-card>
