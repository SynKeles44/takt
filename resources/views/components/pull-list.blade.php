@props(['pulls', 'compact' => false, 'selectable' => false])

@if ($pulls === [])
    <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
        {{ __('app.dev.reviews_empty') }}
    </p>
@else
    <ul @class(['space-y-1.5', 'mt-4' => ! $compact])>
        @foreach ($pulls as $pull)
            <li class="row flex items-start gap-3 px-3 py-2">
                @if ($selectable)
                    <input type="checkbox" class="mt-1 size-3.5 shrink-0 accent-[var(--color-accent)]"
                           data-pull-pick value="{{ $pull['url'] }}" data-title="{{ $pull['title'] }}" checked
                           aria-label="{{ __('app.dev.pick_pull') }}" title="{{ __('app.dev.pick_pull') }}">
                @endif

                <span class="min-w-0 flex-1">
                    {{-- two lines: a conventional-commit title does not survive one --}}
                    <a href="{{ $pull['url'] }}" target="_blank"
                       class="line-clamp-2 text-sm leading-snug text-ink hover:text-accent-text"
                       title="{{ $pull['title'] }}">
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

                <button type="button" class="icon-action shrink-0" data-copy="{{ $pull['url'] }}"
                        data-copy-scope="pull" data-copy-title="{{ $pull['title'] }}"
                        data-copy-label="{{ __('app.dev.link_copied') }}" title="{{ __('app.dev.copy_link') }}"
                        aria-label="{{ __('app.dev.copy_link') }}">
                    <x-icon name="clipboard" class="size-3.5"/>
                </button>
            </li>
        @endforeach
    </ul>
@endif
