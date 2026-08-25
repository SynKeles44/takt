@props(['todo', 'compact' => false])

@php
    $done = $todo->isDone();
    $state = $todo->dueState();
    $accent = $todo->due_at === null ? '' : $state->accentClass();
@endphp

<div data-item data-done="{{ $done ? 1 : 0 }}" @class(['row group relative flex items-start gap-3', $accent])>
    <form method="POST" action="{{ route('todos.toggle', $todo) }}" class="relative z-10 shrink-0 pt-0.5" data-async>
        @csrf
        @method('PATCH')
        <button type="submit" class="check" aria-label="{{ __('app.todos.complete') }}">
            <x-icon name="check" class="size-3"/>
        </button>
    </form>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            {{-- stretched link: the whole row opens the task, the buttons stay clickable --}}
            <a href="{{ route('todos.show', $todo) }}" data-done-strike
               class="truncate text-sm font-medium text-ink after:absolute after:inset-0 after:content-[''] group-hover:underline">
                {{ $todo->title }}
            </a>

            <span data-done-hide>
                <x-due-badge :todo="$todo"/>
            </span>

            @foreach ($todo->tags as $tag)
                <x-tag-badge :tag="$tag"/>
            @endforeach

            @if ($todo->recurrence->repeats())
                <span class="pill" title="{{ $todo->recurrence->label() }}">
                    <x-icon name="repeat" class="size-3.5"/>
                    {{ $todo->recurrence->label() }}
                </span>
            @endif

            @php $progress = $todo->stepProgress(); @endphp
            @if ($progress)
                <span class="pill metric" title="{{ __('app.todos.steps_field') }}">
                    <x-icon name="list-check" class="size-3.5"/>
                    {{ $progress['done'] }}/{{ $progress['total'] }}
                </span>
            @endif
        </div>

        @if ($todo->body && ! $compact)
            <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-muted">{{ $todo->body }}</p>
        @endif
    </div>

    @unless ($compact)
        <div class="relative z-10 flex shrink-0 items-center gap-0.5">

            @if ($todo->due_at !== null && $state->isUrgent())
                <form method="POST" action="{{ route('todos.snooze', $todo) }}" data-live>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="by" value="tomorrow">
                    <button type="submit" class="icon-action" aria-label="{{ __('app.todos.snooze') }}" title="{{ __('app.todos.snooze') }}">
                        <x-icon name="clock" class="size-4"/>
                    </button>
                </form>
            @endif

            <a href="{{ route('todos.edit', $todo) }}" class="icon-action" aria-label="{{ __('app.todos.rename') }}">
                <x-icon name="pencil" class="size-4"/>
            </a>

            <form method="POST" action="{{ route('todos.destroy', $todo) }}" data-confirm="{{ __('app.todos.confirm_delete') }}" data-live>
                @csrf
                @method('DELETE')
                <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                    <x-icon name="trash" class="size-4"/>
                </button>
            </form>
        </div>
    @endunless
</div>
