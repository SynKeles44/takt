<x-app-layout :title="__('app.templates.title')">
    <a href="{{ route('todos.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.tags.back') }}
    </a>

    <div class="stack-grid grid lg:grid-cols-2">
        <x-card class="rise">
            <h2 class="text-base font-semibold text-ink">{{ __('app.templates.new') }}</h2>
            <p class="mt-0.5 text-xs text-muted">{{ __('app.templates.intro') }}</p>

            <form method="POST" action="{{ route('templates.store') }}" class="mt-5 space-y-4" data-live>
                @csrf

                <div>
                    <label for="name" class="label">{{ __('app.templates.name') }}</label>
                    <input id="name" type="text" name="name" value="{{ old('name') }}" class="control" maxlength="80"
                           placeholder="{{ __('app.templates.name_placeholder') }}" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="items" class="label">{{ __('app.templates.items') }}</label>
                    <textarea id="items" name="items" rows="7" class="control" maxlength="4000"
                              placeholder="{{ __('app.templates.items_placeholder') }}">{{ old('items') }}</textarea>
                    <p class="mt-1.5 text-xs text-faint">{{ __('app.templates.items_hint') }}</p>
                    @error('items') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="plus" class="size-4"/>
                    {{ __('app.templates.save') }}
                </button>
            </form>
        </x-card>

        <x-card>
            <h2 class="heading">{{ __('app.templates.list') }}</h2>

            <div class="mt-4 space-y-2">
                @forelse ($templates as $template)
                    <details class="tile group overflow-hidden">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 transition hover:bg-hover [&::-webkit-details-marker]:hidden">
                            <x-icon name="list-check" class="size-4 shrink-0 text-muted"/>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-ink">{{ $template->name }}</span>
                            <span class="pill shrink-0">{{ trans_choice('app.templates.count', $template->items->count()) }}</span>
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-muted transition group-open:rotate-90"/>
                        </summary>

                        <div class="space-y-3 border-t border-line p-4">
                            <ul class="space-y-1 text-xs text-muted">
                                @foreach ($template->items as $item)
                                    <li class="flex items-start gap-2">
                                        <span class="dot mt-1 size-1.5 shrink-0 bg-muted"></span>{{ $item->title }}
                                    </li>
                                @endforeach
                            </ul>

                            <form method="POST" action="{{ route('templates.destroy', $template) }}"
                                  data-confirm="{{ __('app.templates.confirm_delete') }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger w-full text-xs">
                                    <x-icon name="trash" class="size-3.5"/>
                                    {{ __('app.form.delete') }}
                                </button>
                            </form>
                        </div>
                    </details>
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-5 text-center text-xs text-faint">
                        {{ __('app.templates.empty') }}
                    </p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
