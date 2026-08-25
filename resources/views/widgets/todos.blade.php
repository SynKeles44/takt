<x-card>
    @php $urgent = $todos->filter(fn ($todo) => $todo->dueState()->isUrgent()); @endphp

    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.todos.dashboard_title') }}</h2>
        <div class="flex items-center gap-2">
            @if ($urgent->isNotEmpty())
                <span class="pill border-danger/40 bg-danger/15 text-danger-text">
                    <x-icon name="alert" class="size-3.5"/>
                    {{ trans_choice('app.todos.urgent_count', $urgent->count()) }}
                </span>
            @endif
            <a href="{{ route('todos.index') }}" class="pill hover:text-ink">{{ __('app.todos.open_all') }}</a>
        </div>
    </div>

    <div class="mt-4 space-y-2">
        @forelse ($todos->take(6) as $todo)
            <x-todo-row :todo="$todo" compact/>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-6 text-center text-sm text-faint">
                {{ __('app.todos.empty') }}
            </p>
        @endforelse
    </div>

    @if ($todos->count() > 6)
        <p class="mt-3 text-center text-xs text-faint">{{ trans_choice('app.todos.more', $todos->count() - 6) }}</p>
    @endif
</x-card>
