<x-app-layout :title="__('app.tags.title')">
    <a href="{{ route('todos.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.tags.back') }}
    </a>

    <div class="grid stack-grid lg:grid-cols-5">
        <div class="lg:col-span-3">
        <x-card class="rise">
            <div class="flex items-start gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-accent/10 text-accent-text"><x-icon name="tag" class="size-5"/></span>
                <div>
                    <h2 class="text-base font-semibold text-ink">{{ __('app.settings.tags_title') }}</h2>
                    <p class="mt-0.5 text-xs leading-relaxed text-muted">{{ __('app.settings.tags_intro') }}</p>
                </div>
            </div>

            <div class="mt-5 space-y-4">
                @forelse ($tags as $tag)
                    <details class="tile group overflow-hidden">
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3 transition hover:bg-hover [&::-webkit-details-marker]:hidden">
                            <span class="dot size-3 shrink-0 {{ $tag->color->dot() }}"></span>
                            <span class="min-w-0 flex-1 truncate text-sm font-semibold text-ink">{{ $tag->name }}</span>
                            <span class="pill shrink-0">{{ trans_choice('app.tags.usage', $tag->todos_count) }}</span>
                            <x-icon name="chevron-right" class="size-4 shrink-0 text-muted transition group-open:rotate-90"/>
                        </summary>

                        <form method="POST" action="{{ route('tags.update', $tag) }}" class="space-y-4 border-t border-line p-4" data-live>
                        @csrf
                        @method('PUT')

                        <div class="space-y-2">
                            <label for="tag-name-{{ $tag->id }}" class="label">{{ __('app.settings.tag_name') }}</label>
                            <input id="tag-name-{{ $tag->id }}" type="text" name="name" value="{{ $tag->name }}"
                                   class="control" maxlength="60" required>
                        </div>

                        <div class="space-y-2">
                            <span class="label">{{ __('app.settings.tag_color') }}</span>
                            <x-color-choice :colors="$tagColors" :selected="$tag->color"/>
                        </div>

                        <div class="space-y-2">
                            <label for="lead-{{ $tag->id }}" class="label">{{ __('app.settings.warn_lead') }}</label>
                            <select id="lead-{{ $tag->id }}" name="warn_lead_minutes" class="control">
                                @foreach ($leadOptions as $minutes)
                                    <option value="{{ $minutes }}" @selected($tag->warn_lead_minutes === $minutes)>
                                        {{ $minutes === 0
                                            ? __('app.settings.warn_never')
                                            : __('app.settings.warn_before', ['duration' => \App\Support\Duration::human($minutes * 60)]) }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs leading-relaxed text-faint">{{ __('app.settings.warn_lead_hint') }}</p>
                        </div>

                        <div class="space-y-2">
                            <span class="label">{{ __('app.settings.after_due') }}</span>
                            <label class="flex cursor-pointer items-start gap-2.5">
                                <input type="checkbox" name="auto_complete_expired" value="1"
                                       class="mt-0.5 size-4 shrink-0 accent-[var(--color-accent)]" @checked($tag->auto_complete_expired)>
                                <span class="text-xs leading-relaxed text-muted">
                                    <span class="block font-semibold text-ink">{{ __('app.settings.auto_complete') }}</span>
                                    {{ __('app.settings.auto_complete_hint') }}
                                </span>
                            </label>
                        </div>

                        <div class="flex items-center gap-2 border-t border-line pt-3">
                            <button type="submit" class="btn btn-primary flex-1">
                                <x-icon name="check" class="size-4"/>
                                {{ __('app.settings.save') }}
                            </button>

                            <button type="submit" form="delete-tag-{{ $tag->id }}" class="btn btn-danger">
                                <x-icon name="trash" class="size-4"/>
                                {{ __('app.form.delete') }}
                            </button>
                        </div>
                        </form>
                    </details>

                    <form id="delete-tag-{{ $tag->id }}" method="POST" action="{{ route('tags.destroy', $tag) }}" data-live
                          data-confirm="{{ __('app.settings.confirm_delete_tag') }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
                        {{ __('app.settings.tags_empty') }}
                    </p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('tags.store') }}" class="mt-5 space-y-4 border-t border-line pt-5" data-live>
                @csrf

                <h3 class="text-sm font-semibold text-ink">{{ __('app.settings.new_tag') }}</h3>

                <div class="space-y-2">
                    <label for="tag-name" class="label">{{ __('app.settings.tag_name') }}</label>
                    <input id="tag-name" type="text" name="name" value="{{ old('name') }}" class="control"
                           placeholder="{{ __('app.settings.tag_placeholder') }}" maxlength="60" required>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <span class="label">{{ __('app.settings.tag_color') }}</span>
                    <x-color-choice :colors="$tagColors"/>
                </div>

                <div class="space-y-2">
                    <label for="new-lead" class="label">{{ __('app.settings.warn_lead') }}</label>
                    <select id="new-lead" name="warn_lead_minutes" class="control">
                        @foreach ($leadOptions as $minutes)
                            <option value="{{ $minutes }}" @selected($minutes === 60)>
                                {{ $minutes === 0
                                    ? __('app.settings.warn_never')
                                    : __('app.settings.warn_before', ['duration' => \App\Support\Duration::human($minutes * 60)]) }}
                            </option>
                        @endforeach
                    </select>
                    @error('warn_lead_minutes') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <label class="flex cursor-pointer items-start gap-2.5">
                    <input type="checkbox" name="auto_complete_expired" value="1" class="mt-0.5 size-4 shrink-0 accent-[var(--color-accent)]">
                    <span class="text-xs leading-relaxed text-muted">
                        <span class="block font-semibold text-ink">{{ __('app.settings.auto_complete') }}</span>
                        {{ __('app.settings.auto_complete_hint') }}
                    </span>
                </label>

                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="plus" class="size-4"/>
                    {{ __('app.settings.add_tag') }}
                </button>
            </form>
        </x-card>
        </div>

        <div class="lg:col-span-2">
            <x-card class="rise lg:sticky lg:top-8">
                <h2 class="heading">{{ __('app.tags.help_title') }}</h2>

                <ul class="mt-4 space-y-4 text-xs leading-relaxed text-muted">
                    <li>
                        <span class="block font-semibold text-ink">{{ __('app.settings.tag_color') }}</span>
                        {{ __('app.tags.help_color') }}
                    </li>
                    <li>
                        <span class="block font-semibold text-ink">{{ __('app.settings.warn_lead') }}</span>
                        {{ __('app.tags.help_lead') }}
                    </li>
                    <li>
                        <span class="block font-semibold text-ink">{{ __('app.settings.auto_complete') }}</span>
                        {{ __('app.tags.help_auto') }}
                    </li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
