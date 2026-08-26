<x-app-layout :title="__('app.nav.dashboard')">
    <div data-board
         data-arrange-url="{{ route('dashboard.arrange') }}"
         data-widget-url="{{ route('dashboard.widget', ['widget' => '__widget__']) }}"
         data-saved-label="{{ __('app.widget.saved') }}"
         data-discarded-label="{{ __('app.widget.discarded') }}">

        <div class="mb-4 flex items-center justify-end gap-2">
            <span class="pill hidden text-[10px]" data-board-badge>{{ __('app.widget.editing') }}</span>

            <div class="flex items-center gap-2">
                <form method="POST" action="{{ route('dashboard.reset') }}" class="hidden" data-board-reset
                      data-confirm="{{ __('app.widget.confirm_reset') }}" data-live>
                    @csrf
                    <button type="submit" class="btn btn-ghost text-xs">
                        <x-icon name="repeat" class="size-3.5"/>
                        {{ __('app.widget.reset') }}
                    </button>
                </form>

                <button type="button" class="btn btn-ghost text-xs" data-board-toggle>
                    <x-icon name="squares" class="size-3.5"/>
                    <span data-board-toggle-label
                          data-edit-label="{{ __('app.widget.customize') }}"
                          data-done-label="{{ __('app.widget.done') }}">{{ __('app.widget.customize') }}</span>
                </button>
            </div>
        </div>

        <div class="dashboard-grid" data-board-grid>
            @foreach ($widgets as $item)
                <div class="widget-slot" data-widget="{{ $item['widget']->value }}"
                     data-label="{{ $item['widget']->label() }}"
                     style="--widget-span: {{ $item['columns'] }}; --widget-rows: {{ $item['rows'] }}">
                    <div class="widget-body">
                        @include($item['widget']->view(), $item['data'])
                    </div>

                    <div class="widget-tools">
                        <button type="button" class="widget-remove" data-widget-remove
                                aria-label="{{ __('app.widget.remove') }}" title="{{ __('app.widget.remove') }}">
                            <x-icon name="minus" class="size-4"/>
                        </button>

                        <span class="widget-bar">
                            <span class="widget-grip" data-widget-grip title="{{ __('app.widget.drag') }}">
                                <x-icon name="grip" class="size-3.5"/>
                            </span>

                            <span class="widget-size">
                                <button type="button" data-widget-size="span" data-delta="-1" aria-label="{{ __('app.widget.narrower') }}" title="{{ __('app.widget.narrower') }}">
                                    <x-icon name="chevron-left" class="size-3"/>
                                </button>
                                <button type="button" data-widget-size="span" data-delta="1" aria-label="{{ __('app.widget.wider') }}" title="{{ __('app.widget.wider') }}">
                                    <x-icon name="chevron-right" class="size-3"/>
                                </button>
                            </span>
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($widgets->isEmpty())
            <x-card class="rise text-center">
                <p class="text-sm text-faint">{{ __('app.widget.empty_dashboard') }}</p>
                <button type="button" class="btn btn-primary mt-4" data-board-toggle>
                    <x-icon name="plus" class="size-4"/>
                    <span data-board-toggle-label
                          data-edit-label="{{ __('app.widget.customize') }}"
                          data-done-label="{{ __('app.widget.done') }}">{{ __('app.widget.customize') }}</span>
                </button>
            </x-card>
        @endif

        {{-- the drawer slides in from the right while the board is in edit mode --}}
        <aside class="board-drawer" data-board-drawer aria-hidden="true">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-ink">{{ __('app.widget.drawer_title') }}</h2>
                    <p class="mt-0.5 text-[11px] leading-snug text-faint">{{ __('app.widget.drawer_hint') }}</p>
                </div>

                <button type="button" class="icon-action shrink-0" data-board-cancel
                        aria-label="{{ __('app.widget.discard') }}" title="{{ __('app.widget.discard') }}">
                    <x-icon name="close" class="size-4"/>
                </button>
            </div>

            <div class="segmented mt-1" data-gallery-filter>
                <button type="button" class="segment segment-active" data-filter="all">{{ __('app.widget.filter_all') }}</button>
                @foreach ($groups as $group)
                    <button type="button" class="segment" data-filter="{{ $group->value }}">{{ $group->short() }}</button>
                @endforeach
            </div>

            <label class="sr-only" for="gallery-search">{{ __('app.widget.search') }}</label>
            <input id="gallery-search" type="search" class="control text-xs" data-gallery-search
                   placeholder="{{ __('app.widget.search') }}" autocomplete="off">

            <div class="board-drawer-list" data-drawer-list>
                @foreach ($available as $entry)
                    <p class="board-drawer-group" data-group-label="{{ $entry['group']->value }}">{{ $entry['group']->label() }}</p>

                    @foreach ($entry['widgets'] as $widget)
                        <x-widget-card :widget="$widget"/>
                    @endforeach
                @endforeach

                <p class="board-drawer-empty hidden" data-drawer-empty>{{ __('app.widget.all_in_use') }}</p>
            </div>

            {{-- every catalogue card, so a removed tile comes back as a full card without JS building one --}}
            <template data-gallery-pool>
                @foreach ($catalog as $widget)
                    <x-widget-card :widget="$widget" :pooled="true"/>
                @endforeach
            </template>
        </aside>

        {{-- the peek: the real widget, in the size it will have on the board --}}
        <aside class="gallery-peek" data-gallery-peek aria-hidden="true">
            <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-faint">{{ __('app.widget.peek') }}</p>
            <p class="mt-0.5 truncate text-xs font-semibold text-ink" data-peek-label></p>

            <div class="gallery-peek-frame">
                <div class="gallery-peek-stage" data-peek-stage></div>
            </div>
        </aside>
    </div>
</x-app-layout>
