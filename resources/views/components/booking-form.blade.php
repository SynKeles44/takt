@props(['openTodos' => null, 'pattern' => null])

@php
    $field = 'control metric';
    $label = 'label';
    $blocks = [
        ['prefix' => 'work', 'type' => \App\Enums\EntryType::Work, 'icon' => 'briefcase', 'shell' => 'border-work/25 bg-work/5', 'chip' => 'bg-work/10 text-work-text', 'title' => 'text-work-text'],
        ['prefix' => 'break', 'type' => \App\Enums\EntryType::Break, 'icon' => 'coffee', 'shell' => 'border-rest/25 bg-rest/5', 'chip' => 'bg-rest/10 text-rest-text', 'title' => 'text-rest-text'],
    ];
@endphp

<form method="POST" action="{{ route('entries.store') }}" class="space-y-4" data-live>
    @csrf

    @if ($pattern !== null)
        <button type="button" class="btn btn-ghost w-full text-xs" data-fill='@json($pattern)'
                data-fill-label="{{ __('app.form.filled_from', ['date' => \Illuminate\Support\Carbon::parse($pattern['date'])->isoFormat('dd, D. MMM')]) }}">
            <x-icon name="repeat" class="size-3.5"/>
            {{ __('app.form.like_last_time') }}
        </button>
    @endif

    <div>
        <label for="date" class="{{ $label }}">{{ __('app.form.date') }}</label>
        <input id="date" type="date" name="date" value="{{ old('date', now()->toDateString()) }}"
               class="{{ $field }}" required>
        @error('date') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    @foreach ($blocks as $block)
        <div class="rounded-[var(--radius-control)] border p-3.5 {{ $block['shell'] }}">
            <div class="mb-3 flex items-center gap-2">
                <span class="grid size-6 place-items-center rounded-[var(--radius-control)] {{ $block['chip'] }}"><x-icon :name="$block['icon']" class="size-3.5"/></span>
                <span class="heading {{ $block['title'] }}">{{ $block['type']->label() }}</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                @foreach ([['starts_at', __('app.form.start')], ['ends_at', __('app.form.end')]] as [$suffix, $caption])
                    @php $name = $block['prefix'].'_'.$suffix; @endphp
                    <div>
                        <label for="{{ $name }}" class="{{ $label }}">{{ $caption }}</label>
                        <input id="{{ $name }}" type="time" name="{{ $name }}" value="{{ old($name) }}" class="{{ $field }}">
                    </div>
                @endforeach
            </div>

            @error($block['prefix'].'_starts_at') <p class="field-error">{{ $message }}</p> @enderror
            @error($block['prefix'].'_ends_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    @endforeach

    <div>
        <label for="note" class="{{ $label }}">{{ __('app.form.note') }}</label>
        <input id="note" type="text" name="note" value="{{ old('note') }}"
               placeholder="{{ __('app.form.note_placeholder') }}"
               class="control"
               maxlength="500">
        @error('note') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <p class="text-xs leading-relaxed text-faint">{{ __('app.form.optional_hint') }}</p>

    <button type="submit"
            class="btn btn-primary w-full">
        <x-icon name="check" class="size-4"/>
        {{ __('app.form.submit') }}
    </button>
</form>
