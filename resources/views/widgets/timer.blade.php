@use('App\Enums\EntryType')
@use('App\Support\Duration')

@php
    $isWork = $running?->type === EntryType::Work;
    $btnWork = 'btn btn-work btn-lg';
    $btnBreak = 'btn btn-rest btn-lg';
    $btnGhost = 'btn btn-ghost btn-lg';

    $exemptPill = match ($exemption['tone'] ?? null) {
        'accent' => 'border-accent/30 bg-accent/10 text-accent-text',
        'danger' => 'border-danger/30 bg-danger/10 text-danger-text',
        'work' => 'border-work/30 bg-work/10 text-work-text',
        'rest' => 'border-rest/30 bg-rest/10 text-rest-text',
        default => 'border-line bg-raised text-muted',
    };
    $blocking = $exemption !== null && ($exemption['blocking'] ?? true);
@endphp

<x-card class="relative overflow-hidden">
    <div aria-hidden="true"
         class="pointer-events-none absolute -right-24 -top-28 size-72 rounded-full blur-3xl {{ $running === null ? 'bg-accent/10' : ($isWork ? 'bg-work/15' : 'bg-rest/15') }}"></div>

@if (($gap ?? null) !== null)
    <div class="relative mb-4 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-4 py-3">
        <p class="flex items-center gap-2 text-sm font-semibold text-rest-text">
            <x-icon name="alert" class="size-4 shrink-0"/>
            {{ __('app.away.title') }}
        </p>

        <p class="mt-1 text-xs leading-relaxed text-muted">
            {{ __('app.away.body', [
                'from' => $gap->started_at->format('H:i'),
                'to' => $gap->ended_at->format('H:i'),
                'duration' => \App\Support\Duration::human($gap->seconds()),
            ]) }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (['break', 'shorten', 'keep'] as $answer)
                <form method="POST" action="{{ route('away.update', $gap) }}" data-live>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="answer" value="{{ $answer }}">
                    <button type="submit" @class(['btn text-xs', 'btn-primary' => $answer === 'break', 'btn-ghost' => $answer !== 'break'])>
                        {{ __('app.away.'.$answer) }}
                    </button>
                </form>
            @endforeach
        </div>
    </div>
@endif

    <div class="relative flex flex-col gap-8 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            @if ($running)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="pulse-ring dot relative flex size-2.5 {{ $isWork ? 'bg-work text-work' : 'bg-rest text-rest' }}"></span>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] {{ $isWork ? 'text-work-text' : 'text-rest-text' }}">
                        {{ $running->type->label() }}
                    </p>

                    @include('partials.exempt-pill')
                </div>

                <p class="metric mt-3 text-5xl font-bold tracking-tight text-ink sm:text-6xl"
                   data-since="{{ $running->started_at->toIso8601String() }}">
                    {{ Duration::clock($running->durationInSeconds()) }}
                </p>

                <p class="mt-2 text-sm text-muted">
                    {{ __('app.timer.running_since', ['time' => $running->started_at->format('H:i')]) }}
                    @if ($running->note)
                        <span class="text-dim">·</span> {{ $running->note }}
                    @endif
                </p>
            @else
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <span class="dot flex size-2.5 bg-dim"></span>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-faint">
                        {{ __('app.timer.idle_title') }}
                    </p>

                    @include('partials.exempt-pill')
                </div>

                <p class="metric mt-3 text-5xl font-bold tracking-tight text-dim sm:text-6xl">00:00:00</p>

                <p class="mt-2 text-sm text-muted">{{ __('app.timer.idle_hint') }}</p>
            @endif

            @if ($blocking)
                <p class="mt-1.5 text-xs font-medium text-faint">{{ __('app.absence.no_target') }}</p>
            @endif

            @foreach ($hints as $hint)
                <p @class([
                        'mt-2 flex items-start gap-2 text-xs',
                        'text-danger-text' => $hint['level'] === 'danger',
                        'text-rest-text' => $hint['level'] !== 'danger',
                    ])>
                    <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0"/>
                    <span>{{ $hint['text'] }}</span>
                </p>
            @endforeach
        </div>

        <div class="flex shrink-0 flex-col gap-2.5 sm:flex-row">
            @if ($running === null)
                <form method="POST" action="{{ route('timer.start') }}" data-live>
                    @csrf
                    <input type="hidden" name="type" value="{{ EntryType::Work->value }}">
                    <button type="submit" class="{{ $btnWork }} w-full">
                        <x-icon name="play" class="size-4"/>
                        {{ __('app.timer.start_work') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('timer.start') }}" data-live>
                    @csrf
                    <input type="hidden" name="type" value="{{ EntryType::Break->value }}">
                    <button type="submit" class="{{ $btnGhost }} w-full">
                        <x-icon name="coffee" class="size-4"/>
                        {{ __('app.timer.start_break') }}
                    </button>
                </form>
            @else
                <form method="POST" action="{{ route('timer.start') }}" data-live>
                    @csrf
                    <input type="hidden" name="type" value="{{ $running->type->opposite()->value }}">
                    <button type="submit" class="{{ $isWork ? $btnBreak : $btnWork }} w-full">
                        <x-icon :name="$isWork ? 'pause' : 'play'" class="size-4"/>
                        {{ $isWork ? __('app.timer.to_break') : __('app.timer.back_to_work') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('timer.stop') }}" data-live>
                    @csrf
                    <button type="submit" class="{{ $btnGhost }} w-full">
                        <x-icon name="stop" class="size-4"/>
                        {{ __('app.timer.end_day') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</x-card>
