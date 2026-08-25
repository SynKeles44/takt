@props(['entry'])

@php $running = $entry->isRunning(); @endphp

<div class="row group flex items-center gap-2 sm:gap-3">
    <x-type-badge :type="$entry->type" :running="$running" class="shrink-0"/>

    <div class="metric flex shrink-0 items-baseline gap-1.5 text-sm text-ink">
        <span>{{ $entry->started_at->format('H:i') }}</span>
        <span class="text-dim">–</span>
        <span class="{{ $running ? 'text-faint' : '' }}">{{ $entry->ended_at?->format('H:i') ?? '···' }}</span>
    </div>

    <p class="hidden min-w-0 flex-1 truncate text-sm text-muted sm:block" title="{{ $entry->note }}">{{ $entry->note }}</p>

    <span class="metric ml-auto shrink-0 text-sm font-semibold text-ink"
          @if ($running) data-since="{{ $entry->started_at->toIso8601String() }}" data-format="human" @endif>
        {{ \App\Support\Duration::human($entry->durationInSeconds()) }}
    </span>

    <div class="flex shrink-0 items-center gap-0.5">
        <a href="{{ route('entries.edit', $entry) }}" class="icon-action" aria-label="{{ __('app.edit.title') }}">
            <x-icon name="pencil" class="size-4"/>
        </a>

        <form method="POST" action="{{ route('entries.destroy', $entry) }}" data-confirm="{{ __('app.form.confirm_delete') }}" data-live>
            @csrf
            @method('DELETE')
            <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                <x-icon name="trash" class="size-4"/>
            </button>
        </form>
    </div>
</div>
