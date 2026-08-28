<x-app-layout :title="__('app.dev.snippets')" :wide="true">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.dev.snippets') }}</h2>
                <p class="mt-0.5 text-xs text-faint">{{ __('app.dev.snippets_hint') }}</p>
            </div>

            <x-dev-tabs active="snippets"/>
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-[1.3fr_1fr]">
        <x-card class="rise">
            <h2 class="heading">{{ __('app.dev.stored') }}</h2>

            @forelse ($snippets as $snippet)
                <div class="mt-3">
                    <div class="row flex items-start gap-3 px-3 py-2">
                        <button type="button" class="min-w-0 flex-1 text-left"
                                data-copy="{{ $snippet->body }}" data-copy-ping="{{ route('snippets.used', $snippet) }}"
                                data-copy-label="{{ __('app.dev.copied') }}" title="{{ __('app.dev.copy') }}">
                            <span class="block truncate text-sm font-medium text-ink">{{ $snippet->title }}</span>
                            <span class="metric mt-0.5 block whitespace-pre-wrap break-all text-[11px] leading-relaxed text-muted">{{ $snippet->body }}</span>
                        </button>

                        <span class="flex shrink-0 items-center gap-1.5">
                            @if ($snippet->label)<span class="pill text-[10px]">{{ $snippet->label }}</span>@endif
                            @if ($snippet->uses > 0)<span class="metric text-[10px] text-dim">{{ $snippet->uses }}×</span>@endif

                            <form method="POST" action="{{ route('snippets.destroy', $snippet) }}"
                                  data-confirm="{{ __('app.dev.confirm_delete_snippet') }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                                    <x-icon name="trash" class="size-3.5"/>
                                </button>
                            </form>
                        </span>
                    </div>
                </div>
            @empty
                <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
                    {{ __('app.dev.no_snippets') }}
                </p>
            @endforelse
        </x-card>

        <x-card class="rise self-start">
            <h2 class="heading">{{ __('app.dev.new_snippet') }}</h2>

            <form method="POST" action="{{ route('snippets.store') }}" class="mt-4 space-y-3" data-live>
                @csrf

                <div>
                    <label for="title" class="label">{{ __('app.dev.snippet_title') }}</label>
                    <input id="title" type="text" name="title" value="{{ old('title') }}" class="control" maxlength="120" required data-refocus>
                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="body" class="label">{{ __('app.dev.snippet_body') }}</label>
                    <textarea id="body" name="body" rows="4" class="control metric text-xs" maxlength="4000" required>{{ old('body') }}</textarea>
                    @error('body') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="label" class="label">{{ __('app.dev.snippet_label') }}</label>
                    <input id="label" type="text" name="label" value="{{ old('label') }}" class="control text-xs" maxlength="40" placeholder="docker, git, ssh …">
                </div>

                <button type="submit" class="btn btn-primary w-full">
                    <x-icon name="plus" class="size-4"/>
                    {{ __('app.dev.add') }}
                </button>
            </form>
        </x-card>
    </div>
</x-app-layout>
