<x-card>
    <form method="POST" action="{{ route('notes.store') }}" class="space-y-3" data-live>
        @csrf
        <div class="flex items-center justify-between gap-3">
            <h2 class="heading">{{ __('app.notes.title') }}</h2>
            <span class="pill">{{ $today->isoFormat('D. MMM') }}</span>
        </div>

        <input type="hidden" name="day" value="{{ $today->toDateString() }}">
        <textarea name="body" rows="4" class="control" maxlength="2000"
                  placeholder="{{ __('app.notes.placeholder') }}">{{ old('body', $dayNote?->body) }}</textarea>
        @error('body') <p class="field-error">{{ $message }}</p> @enderror

        <button type="submit" class="btn btn-ghost w-full text-xs">
            <x-icon name="check" class="size-4"/>
            {{ __('app.notes.save') }}
        </button>
    </form>
</x-card>
