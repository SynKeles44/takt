<x-app-layout :title="__('app.edit.title')">
    <a href="{{ route('history', ['from' => $entry->started_at->copy()->startOfWeek()->toDateString()]) }}"
       class="inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.edit.back') }}
    </a>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <x-card class="rise">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-ink">{{ __('app.edit.title') }}</h2>
                <x-type-badge :type="$entry->type" :running="$entry->isRunning()"/>
            </div>

            <div class="mt-5">
                <x-entry-form :action="route('entries.update', $entry)"
                              method="PUT"
                              :entry="$entry"
                              :types="$types"
                              :submit-label="__('app.form.save')"
                              :cancel-url="route('history', ['from' => $entry->started_at->copy()->startOfWeek()->toDateString()])"/>
            </div>
        </x-card>

        <x-card class="rise flex flex-col gap-4 self-start">
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-faint">{{ __('app.form.date') }}</span>
                    <span class="font-mono text-ink tabular-nums">{{ $entry->started_at->isoFormat('dddd, D. MMMM YYYY') }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-faint">{{ __('app.form.start') }} – {{ __('app.form.end') }}</span>
                    <span class="font-mono text-ink tabular-nums">
                        {{ $entry->started_at->format('H:i') }} – {{ $entry->ended_at?->format('H:i') ?? __('app.running') }}
                    </span>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-line pt-3">
                    <span class="text-faint">{{ __('app.form.duration') }}</span>
                    <span class="font-mono text-lg font-bold text-ink tabular-nums">
                        {{ \App\Support\Duration::human($entry->durationInSeconds()) }}
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('entries.destroy', $entry) }}" data-confirm="{{ __('app.form.confirm_delete') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger w-full">
                    <x-icon name="trash" class="size-4"/>
                    {{ __('app.form.delete') }}
                </button>
            </form>
        </x-card>
    </div>
</x-app-layout>
