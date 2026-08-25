<x-card>
    <h2 class="heading">{{ __('app.widget.todo_progress.label') }}</h2>

    @php $share = $steps['total'] > 0 ? round($steps['done'] / $steps['total'] * 100) : 0; @endphp

    <div class="mt-4 grid grid-cols-3 gap-2 text-center">
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold text-ink">{{ $open }}</p>
            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-faint">{{ __('app.todos.filter_open') }}</p>
        </div>
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold {{ $urgent > 0 ? 'text-danger-text' : 'text-dim' }}">{{ $urgent }}</p>
            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.todo_progress.urgent') }}</p>
        </div>
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold text-work-text">{{ $doneThisWeek }}</p>
            <p class="mt-0.5 text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.todo_progress.done_week') }}</p>
        </div>
    </div>

    @if ($steps['total'] > 0)
        <div class="mt-4">
            <div class="flex items-center justify-between text-[11px] text-faint">
                <span>{{ __('app.widget.todo_progress.steps') }}</span>
                <span class="metric">{{ $steps['done'] }} / {{ $steps['total'] }}</span>
            </div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-[var(--radius-pill)] bg-raised">
                <div class="h-full rounded-[var(--radius-pill)] bg-gradient-to-r from-work to-work-2" style="width: {{ $share }}%"></div>
            </div>
        </div>
    @endif
</x-card>
