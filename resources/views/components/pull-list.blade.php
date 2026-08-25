@props(['pulls', 'compact' => false])

@if ($pulls === [])
    <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
        {{ __('app.dev.reviews_empty') }}
    </p>
@else
    <ul @class(['space-y-1.5', 'mt-4' => ! $compact])>
        @foreach ($pulls as $pull)
            <li class="row flex items-start gap-3 px-3 py-2">
                <span class="min-w-0 flex-1">
                    <a href="{{ $pull['url'] }}" target="_blank" class="block truncate text-sm text-ink hover:text-accent-text">
                        {{ $pull['title'] }}
                    </a>
                    <span class="block truncate text-[11px] text-dim">
                        {{ $compact ? '#'.$pull['number'] : $pull['repository'].' #'.$pull['number'] }}
                        @if ($pull['draft']) · {{ __('app.dev.draft') }} @endif
                    </span>
                </span>

                <span @class([
                        'pill shrink-0 text-[10px]',
                        'border-danger/40 bg-danger/10 text-danger-text' => $pull['updated_at']->diffInHours() >= 24,
                    ])>
                    {{ $pull['updated_at']->diffForHumans(['short' => true, 'parts' => 1]) }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
