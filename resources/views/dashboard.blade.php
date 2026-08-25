<x-app-layout :title="__('app.nav.dashboard')">
    <div data-board
         data-arrange-url="{{ route('dashboard.arrange') }}"
         data-saved-label="{{ __('app.widget.saved') }}">

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

                <button type="button" class="icon-action shrink-0" data-board-close aria-label="{{ __('app.palette.close') }}">
                    <x-icon name="close" class="size-4"/>
                </button>
            </div>

            <div class="board-drawer-list" data-drawer-list>
                @forelse ($available as $entry)
                    <p class="board-drawer-group">{{ $entry['group']->label() }}</p>

                    @foreach ($entry['widgets'] as $widget)
                        <button type="button" class="board-chip" data-add-widget="{{ $widget->value }}"
                                data-span="{{ $widget->span() }}" data-rows="{{ $widget->rows() }}">
                            <x-icon name="plus" class="size-3.5 shrink-0 text-accent-text"/>
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-ink">{{ $widget->label() }}</span>
                                <span class="mt-0.5 block text-[11px] leading-snug text-dim">{{ $widget->description() }}</span>
                            </span>
                        </button>
                    @endforeach
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-6 text-center text-xs text-faint">
                        {{ __('app.widget.all_in_use') }}
                    </p>
                @endforelse
            </div>
        </aside>
    </div>
</x-app-layout>
