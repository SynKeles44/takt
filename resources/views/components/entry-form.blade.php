@props([
    'action',
    'method' => 'POST',
    'entry' => null,
    'types',
    'submitLabel',
    'cancelUrl' => null,
])

@php
    $field = 'control';
    $label = 'label';

    $currentType = old('type', $entry?->type->value ?? \App\Enums\EntryType::Work->value);
    $currentDate = old('date', ($entry?->started_at ?? now())->toDateString());
    $currentStart = old('starts_at', ($entry?->started_at ?? now()->subHour())->format('H:i'));
    $currentEnd = old('ends_at', ($entry?->ended_at ?? now())->format('H:i'));
@endphp

<form method="POST" action="{{ $action }}" class="space-y-4" data-live>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <span class="{{ $label }}">{{ __('app.form.type') }}</span>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($types as $type)
                @php
                    $checkedClasses = $type === \App\Enums\EntryType::Work
                        ? 'peer-checked:border-work/50 peer-checked:bg-work/10 peer-checked:text-work-text'
                        : 'peer-checked:border-rest/50 peer-checked:bg-rest/10 peer-checked:text-rest-text';
                @endphp
                <label class="block">
                    <input type="radio" name="type" value="{{ $type->value }}" class="peer sr-only"
                           @checked($currentType === $type->value)>
                    <span class="control flex cursor-pointer items-center justify-center gap-2 text-center font-medium text-muted transition {{ $checkedClasses }}">
                        <x-icon :name="$type === \App\Enums\EntryType::Work ? 'briefcase' : 'coffee'" class="size-4"/>
                        {{ $type->label() }}
                    </span>
                </label>
            @endforeach
        </div>
        @error('type') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="date" class="{{ $label }}">{{ __('app.form.date') }}</label>
        <input id="date" type="date" name="date" value="{{ $currentDate }}" class="{{ $field }}" required>
        @error('date') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="starts_at" class="{{ $label }}">{{ __('app.form.start') }}</label>
            <input id="starts_at" type="time" name="starts_at" value="{{ $currentStart }}" class="{{ $field }} metric" required>
            @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="ends_at" class="{{ $label }}">{{ __('app.form.end') }}</label>
            <input id="ends_at" type="time" name="ends_at" value="{{ $currentEnd }}" class="{{ $field }} metric" required>
            @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div>
        <label for="note" class="{{ $label }}">{{ __('app.form.note') }}</label>
        <input id="note" type="text" name="note" value="{{ old('note', $entry?->note) }}"
               placeholder="{{ __('app.form.note_placeholder') }}" class="{{ $field }}" maxlength="500">
        @error('note') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <p class="text-xs leading-relaxed text-faint">{{ __('app.form.neighbour_hint') }}</p>
    <p class="text-xs leading-relaxed text-faint">{{ __('app.form.overnight_hint') }}</p>

    <div class="flex gap-2 pt-1">
        <button type="submit"
                class="btn btn-primary flex-1">
            <x-icon name="check" class="size-4"/>
            {{ $submitLabel }}
        </button>

        @if ($cancelUrl)
            <a href="{{ $cancelUrl }}"
               class="btn btn-ghost">
                {{ __('app.form.cancel') }}
            </a>
        @endif
    </div>
</form>
