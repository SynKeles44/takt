@php
    $links = [
        ['label' => __('app.nav.dashboard'), 'url' => route('dashboard'), 'icon' => 'clock'],
        ['label' => __('app.nav.history'), 'url' => route('history'), 'icon' => 'calendar'],
        ['label' => __('app.nav.calendar'), 'url' => route('calendar'), 'icon' => 'calendar-days'],
        ['label' => __('app.nav.todos'), 'url' => route('todos.index'), 'icon' => 'list-check'],
        ['label' => __('app.tags.manage'), 'url' => route('tags.index'), 'icon' => 'tag'],
        ['label' => __('app.insights.title'), 'url' => route('insights'), 'icon' => 'chart'],
        ['label' => __('app.insights.month'), 'url' => route('insights', ['zeitraum' => 'monat']), 'icon' => 'calendar-days'],
        ['label' => __('app.insights.year'), 'url' => route('insights', ['zeitraum' => 'jahr']), 'icon' => 'grid'],
        ['label' => __('app.month.timesheet'), 'url' => route('month.timesheet'), 'icon' => 'printer'],
        ['label' => __('app.nav.settings'), 'url' => route('settings'), 'icon' => 'gear'],
        ['label' => __('app.trash.title'), 'url' => route('trash'), 'icon' => 'trash'],
        ['label' => __('app.month.backup_action'), 'url' => route('backup'), 'icon' => 'download'],
    ];
@endphp

<div class="pointer-events-none fixed inset-0 z-[60] hidden items-start justify-center p-4 pt-[12vh] sm:p-6 sm:pt-[14vh]"
     data-palette role="dialog" aria-modal="true" aria-label="{{ __('app.palette.title') }}">
    <div class="absolute inset-0 bg-canvas/70 backdrop-blur-sm" data-palette-close></div>

    <div class="surface-plain pointer-events-auto relative w-full max-w-lg overflow-hidden p-0">
        <div class="flex items-center gap-2.5 border-b border-line px-4 py-3">
            <x-icon name="search" class="size-4 shrink-0 text-muted"/>
            <input type="text" data-palette-input autocomplete="off" spellcheck="false"
                   placeholder="{{ __('app.palette.placeholder') }}"
                   class="min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-ink outline-none placeholder:text-faint">
            <span class="pill hidden shrink-0 text-[10px] sm:inline-flex">esc</span>

            <button type="button" class="icon-action shrink-0" data-palette-close aria-label="{{ __('app.palette.close') }}">
                <x-icon name="close" class="size-4"/>
            </button>
        </div>

        <form method="POST" action="{{ route('timer.start') }}" id="palette-work" class="hidden" data-live>
            @csrf<input type="hidden" name="type" value="work">
        </form>
        <form method="POST" action="{{ route('timer.start') }}" id="palette-break" class="hidden" data-live>
            @csrf<input type="hidden" name="type" value="break">
        </form>
        <form method="POST" action="{{ route('timer.stop') }}" id="palette-stop" class="hidden" data-live>
            @csrf
        </form>

        <div class="max-h-[52vh] overflow-y-auto p-2" data-palette-scroll data-palette-search="{{ route('search') }}">
        <ul class="hidden" data-palette-results></ul>

        <template data-palette-template>
            <li data-palette-item data-remote>
                <a href="#" class="flex w-full items-start gap-2.5 rounded-[var(--radius-control)] px-3 py-2 text-sm text-ink transition data-[active]:bg-hover hover:bg-hover">
                    <x-icon name="search" class="mt-0.5 size-4 shrink-0 text-muted"/>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate" data-slot="label"></span>
                        <span class="block truncate text-[11px] text-dim" data-slot="hint"></span>
                    </span>
                    <span class="pill shrink-0 text-[10px]" data-slot="group"></span>
                </a>
            </li>
        </template>

        <ul data-palette-list>
            @foreach ([
                ['label' => __('app.timer.start_work'), 'form' => 'palette-work', 'icon' => 'play'],
                ['label' => __('app.timer.start_break'), 'form' => 'palette-break', 'icon' => 'coffee'],
                ['label' => __('app.timer.stop'), 'form' => 'palette-stop', 'icon' => 'stop'],
            ] as $action)
                <li data-palette-item data-label="{{ Str::lower($action['label']) }}">
                    <button type="submit" form="{{ $action['form'] }}"
                            class="flex w-full items-center gap-2.5 rounded-[var(--radius-control)] px-3 py-2 text-left text-sm text-ink transition data-[active]:bg-hover hover:bg-hover">
                        <x-icon :name="$action['icon']" class="size-4 shrink-0 text-muted"/>
                        {{ $action['label'] }}
                    </button>
                </li>
            @endforeach

            @foreach ($links as $link)
                <li data-palette-item data-label="{{ Str::lower($link['label']) }}">
                    <a href="{{ $link['url'] }}"
                       class="flex w-full items-center gap-2.5 rounded-[var(--radius-control)] px-3 py-2 text-sm text-ink transition data-[active]:bg-hover hover:bg-hover">
                        <x-icon :name="$link['icon']" class="size-4 shrink-0 text-muted"/>
                        {{ $link['label'] }}
                    </a>
                </li>
            @endforeach

        </ul>

        <p class="hidden px-3 py-6 text-center text-xs text-faint" data-palette-empty>
            {{ __('app.palette.empty') }}
        </p>
        </div>
    </div>
</div>
