<x-card>
    <h2 class="heading">{{ __('app.widget.absences.label') }}</h2>

    <div class="mt-4 space-y-2">
        @forelse ($absences as $absence)
            <div class="row flex items-center gap-3 px-3 py-2">
                <span @class([
                        'dot size-2 shrink-0',
                        'bg-accent' => $absence['tone'] === 'accent',
                        'bg-danger' => $absence['tone'] === 'danger',
                        'bg-work' => $absence['tone'] === 'work',
                        'bg-line-strong' => ! in_array($absence['tone'], ['accent', 'danger', 'work'], true),
                    ])></span>
                <span class="min-w-0 flex-1 truncate text-xs text-ink">{{ $absence['label'] }}</span>
                <span class="metric shrink-0 text-[11px] text-faint">{{ $absence['date']->isoFormat('dd, D. MMM') }}</span>
            </div>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ __('app.widget.absences.empty') }}
            </p>
        @endforelse
    </div>
</x-card>
