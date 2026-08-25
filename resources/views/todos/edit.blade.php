<x-app-layout :title="__('app.todos.edit_title')">
    <a href="{{ route('todos.show', $todo) }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.todos.back_to_task') }}
    </a>

    <x-card class="rise lg:max-w-2xl">
        <x-todo-form :action="route('todos.update', $todo)"
                     method="PUT"
                     :todo="$todo"
                     :tags="$tags"
                     :submit-label="__('app.settings.save')"
                     :cancel-url="route('todos.show', $todo)"/>
    </x-card>
</x-app-layout>
