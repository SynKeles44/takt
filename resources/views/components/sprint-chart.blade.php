@props(['days', 'height' => 'h-36'])

@php
    /*
     * The sprint, drawn the way Linear draws one: a scope line across the top, the started count
     * as a filled area under it, the closed count as a line inside that, the ideal dashed, and a
     * bar per day for what actually closed that day.
     *
     * Plotted into a 0–100 box with `preserveAspectRatio="none"`, so the chart is whatever width
     * it is given — which is the thing the previous version got wrong, where every day was a flex
     * child and a full-window card turned fourteen days into fourteen slabs.
     *
     * Strokes carry `vector-effect="non-scaling-stroke"` because that stretch would otherwise
     * squash a 2px line to a hairline horizontally and a band vertically.
     */
    $rows = collect($days);

    // headroom above the scope line, so it is a line in the chart rather than its top edge
    $peak = max(1, (int) $rows->max('scope'), (int) $rows->max('started')) * 1.12;
    $step = $rows->count() > 1 ? 100 / ($rows->count() - 1) : 100;

    $y = fn (float $value): float => round(100 - $value / $peak * 100, 2);
    $x = fn (int $i): float => round($i * $step, 2);

    $drawn = $rows->filter(fn (array $day): bool => ! $day['future'])->values();

    $line = fn (string $field): string => $drawn
        ->map(fn (array $day, int $i): string => $x($i).','.$y((float) $day[$field]))
        ->implode(' ');

    // the area needs its curve closed along the baseline to be a shape rather than a stroke
    $area = $drawn->isEmpty() ? '' : $line('started').' '.$x($drawn->count() - 1).',100 0,100';

    $today = $drawn->count() - 1;
    $closedPeak = max(1, (int) $rows->max('closed'));

    // a day's band, clamped to the box so the first and last one do not paint outside it
    $band = function (int $i) use ($x, $step): array {
        $from = max(0.0, $x($i) - $step / 2);

        return [$from, min(100.0, $x($i) + $step / 2) - $from];
    };
@endphp

<div {{ $attributes->class(['relative w-full', $height]) }}>
    {{--
        The day bands — the future hatched, the weekends shaded — drawn as elements rather than
        inside the SVG. `patternUnits="userSpaceOnUse"` sounds like it escapes the stretch and does
        not: the pattern lives in the user space of the stretched viewBox, so on a full-window card
        a 45° hatch came out as a dense near-horizontal mesh. A CSS gradient is in real pixels.
    --}}
    @foreach ($rows as $i => $day)
        @php([$from, $width] = $band($i))
        @if ($day['future'])
            <span class="pointer-events-none absolute inset-y-0"
                  style="inset-inline-start: {{ $from }}%; inline-size: {{ $width }}%;
                         background: repeating-linear-gradient(45deg, transparent 0 7px, var(--color-line) 7px 8px)"></span>
        @elseif ($day['weekend'])
            <span class="pointer-events-none absolute inset-y-0 bg-[var(--color-line)] opacity-30"
                  style="inset-inline-start: {{ $from }}%; inline-size: {{ $width }}%"></span>
        @endif
    @endforeach

    <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="relative size-full" aria-hidden="true">
        {{-- the scope: flat, because the API gives it as it stands now and not as it stood each day --}}
        <polyline points="0,{{ $y((float) $rows->first()['scope']) }} 100,{{ $y((float) $rows->first()['scope']) }}"
                  fill="none" stroke="var(--color-muted)" stroke-width="1.5" opacity=".7"
                  vector-effect="non-scaling-stroke"/>

        @if ($area !== '')
            <polygon points="{{ $area }}" fill="var(--color-accent)" opacity=".22"/>
            <polyline points="{{ $line('started') }}" fill="none" stroke="var(--color-accent)"
                      stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
        @endif

        <polyline points="{{ $rows->map(fn (array $day, int $i): string => $x($i).','.$y((float) $day['ideal']))->implode(' ') }}"
                  fill="none" stroke="var(--color-accent-2)" stroke-width="1.5" stroke-dasharray="4 4"
                  opacity=".55" vector-effect="non-scaling-stroke"/>

        @if ($drawn->isNotEmpty())
            <polyline points="{{ $line('done') }}" fill="none" stroke="var(--color-work)" stroke-width="2.5"
                      stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>
        @endif
    </svg>

    {{--
        What closed on each day, as a bar on the baseline — drawn as elements rather than inside the
        SVG, because a rect in a stretched viewBox is as wide as the card is, and the same markup
        turned a slim bar into a block on a full-window card.
    --}}
    @foreach ($rows as $i => $day)
        @if ($day['closed'] > 0)
            <span class="pointer-events-none absolute bottom-0 w-1.5 -translate-x-1/2 rounded-t-[2px] bg-accent-2/80"
                  style="inset-inline-start: {{ $x($i) }}%; block-size: {{ round($day['closed'] / $closedPeak * 22, 1) }}%"></span>
        @endif
    @endforeach

    {{-- today, as a dot on the done curve — the one point a glance is looking for --}}
    @if ($drawn->isNotEmpty() && $today >= 0)
        <span class="pointer-events-none absolute size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-work ring-2 ring-[var(--color-surface)]"
              style="inset-inline-start: {{ $x($today) }}%; inset-block-start: {{ $y((float) $drawn->last()['done']) }}%"></span>
    @endif
</div>
