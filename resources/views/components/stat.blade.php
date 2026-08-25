@props([
    'label',
    'seconds' => 0,
    'value' => null,
    'base' => null,
    'since' => null,
    'tone' => 'accent',
    'targetSeconds' => null,
    'hint' => null,
])

@php
    $tones = [
        'accent' => ['text' => 'text-accent-text', 'bar' => 'from-accent to-accent-2', 'ring' => 'bg-accent/10 text-accent-text'],
        'work' => ['text' => 'text-work-text', 'bar' => 'from-work to-work-2', 'ring' => 'bg-work/10 text-work-text'],
        'rest' => ['text' => 'text-rest-text', 'bar' => 'from-rest to-rest-2', 'ring' => 'bg-rest/10 text-rest-text'],
        'danger' => ['text' => 'text-danger-text', 'bar' => 'from-danger to-danger', 'ring' => 'bg-danger/10 text-danger-text'],
        'neutral' => ['text' => 'text-ink', 'bar' => 'from-muted to-muted', 'ring' => 'bg-muted/10 text-muted'],
    ];

    $palette = $tones[$tone] ?? $tones['accent'];
    $progress = $targetSeconds > 0 ? min(100, (int) round($seconds / $targetSeconds * 100)) : null;
@endphp

<x-card class="flex flex-col gap-3">
    <div class="flex items-start justify-between gap-3">
        <p class="heading">{{ $label }}</p>
        <span class="grid size-8 shrink-0 place-items-center rounded-[var(--radius-control)] {{ $palette['ring'] }}">
            {{ $icon ?? '' }}
        </span>
    </div>

    <p class="metric text-3xl font-bold tracking-tight {{ $palette['text'] }}"
       @if ($since) data-since="{{ $since }}" data-base="{{ $base ?? $seconds }}" data-format="human" @endif>
        {{ $value ?? \App\Support\Duration::human($seconds) }}
    </p>

    @if ($progress !== null)
        <div class="space-y-1.5">
            <div class="h-1.5 overflow-hidden rounded-[var(--radius-pill)] bg-hover">
                <div class="h-full rounded-[var(--radius-pill)] bg-gradient-to-r {{ $palette['bar'] }} transition-[width] duration-700"
                     style="width: {{ $progress }}%"></div>
            </div>
            <p class="text-xs text-faint">{{ $hint }}</p>
        </div>
    @elseif ($hint)
        <p class="text-xs text-faint">{{ $hint }}</p>
    @endif
</x-card>
