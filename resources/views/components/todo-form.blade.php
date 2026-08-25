@props(['action', 'method' => 'POST', 'todo' => null, 'tags', 'submitLabel', 'cancelUrl' => null])

<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="title" class="label">{{ __('app.todos.title_field') }}</label>
        <input id="title" type="text" name="title" value="{{ old('title', $todo?->title) }}"
               class="control" required maxlength="200" autofocus>
        @error('title') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="body" class="label">{{ __('app.todos.body_field') }}</label>
        <textarea id="body" name="body" rows="4" class="control" maxlength="2000"
                  placeholder="{{ __('app.todos.body_placeholder') }}">{{ old('body', $todo?->body) }}</textarea>
        @error('body') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="due_date" class="label">{{ __('app.todos.due_date') }}</label>
            <input id="due_date" type="date" name="due_date" class="control metric"
                   value="{{ old('due_date', $todo?->due_at?->toDateString()) }}">
            @error('due_date') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="due_time" class="label">{{ __('app.todos.due_time') }}</label>
            <input id="due_time" type="time" name="due_time" class="control metric"
                   value="{{ old('due_time', $todo?->due_has_time ? $todo->due_at->format('H:i') : null) }}">
            @error('due_time') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="recurrence" class="label">{{ __('app.todos.recurrence_field') }}</label>
        <select id="recurrence" name="recurrence" class="control">
            @foreach (\App\Enums\Recurrence::cases() as $option)
                <option value="{{ $option->value }}" @selected(old('recurrence', $todo?->recurrence?->value ?? 'none') === $option->value)>
                    {{ $option->label() }}
                </option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-faint">{{ __('app.todos.recurrence_hint') }}</p>
    </div>

    <div>
        <span class="label">{{ __('app.todos.tags_field') }}</span>

        @if ($tags->isEmpty())
            <p class="text-xs text-faint">
                {{ __('app.todos.no_tags') }}
                <a href="{{ route('tags.index') }}" class="font-semibold text-accent-text hover:underline">{{ __('app.todos.create_tags') }}</a>
            </p>
        @else
            @php $selected = old('tags', $todo ? $todo->tags->modelKeys() : []); @endphp
            <div class="flex flex-wrap gap-2">
                @foreach ($tags as $tag)
                    <label class="cursor-pointer">
                        <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer sr-only"
                               @checked(in_array((string) $tag->id, array_map('strval', (array) $selected), true))>
                        <span class="pill opacity-45 transition peer-checked:opacity-100 {{ $tag->color->classes() }}">
                            <span class="dot size-1.5 {{ $tag->color->dot() }}"></span>
                            {{ $tag->name }}
                        </span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('tags.*') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="flex gap-2 pt-1">
        <button type="submit" class="btn btn-primary flex-1">
            <x-icon name="check" class="size-4"/>
            {{ $submitLabel }}
        </button>

        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}" class="btn btn-ghost">{{ __('app.form.cancel') }}</a>
        @endif
    </div>
</form>
