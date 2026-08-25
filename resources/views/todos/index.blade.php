<x-app-layout :title="__('app.nav.todos')">
    <div class="stack-grid grid lg:grid-cols-5">
        <div class="stack lg:col-span-3" data-todo-list data-filter="{{ $filter }}">
            <x-card class="rise">
                <form method="POST" action="{{ route('todos.store') }}" class="space-y-3" data-live>
                    @csrf

                    <div class="flex flex-wrap items-center gap-2 sm:flex-nowrap">
                        <label for="title" class="sr-only">{{ __('app.todos.new') }}</label>
                        <input id="title" type="text" name="title" value="{{ old('title') }}"
                               placeholder="{{ __('app.todos.placeholder') }}"
                               class="control min-w-0 flex-1" required maxlength="200" autofocus autocomplete="off" data-refocus>

                        <button type="submit" class="btn btn-primary shrink-0">
                            <x-icon name="plus" class="size-4"/>
                            {{ __('app.todos.add') }}
                        </button>
                    </div>

                    <div class="grid grid-cols-2 items-end gap-2 sm:flex sm:flex-wrap">
                        <div class="sm:w-36">
                            <label for="due_date" class="label">{{ __('app.todos.due_date') }}</label>
                            <input id="due_date" type="date" name="due_date" value="{{ old('due_date') }}" class="control metric">
                        </div>
                        <div class="sm:w-28">
                            <label for="due_time" class="label">{{ __('app.todos.due_time') }}</label>
                            <input id="due_time" type="time" name="due_time" value="{{ old('due_time') }}" class="control metric">
                        </div>

                        @if ($templates->isNotEmpty())
                            <div class="col-span-2 sm:w-44">
                                <label for="step_template_id" class="label">{{ __('app.templates.field') }}</label>
                                <select id="step_template_id" name="step_template_id" class="control text-xs">
                                    <option value="">{{ __('app.templates.none') }}</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('step_template_id') == $template->id)>
                                            {{ $template->name }} · {{ trans_choice('app.templates.count', $template->items->count()) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        @if ($tags->isNotEmpty())
                            <div class="col-span-2 min-w-0 sm:flex-1">
                                <span class="label">{{ __('app.todos.tags_field') }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($tags as $tag)
                                        <label class="cursor-pointer">
                                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}" class="peer sr-only">
                                            <span class="pill opacity-45 transition peer-checked:opacity-100 {{ $tag->color->classes() }}">
                                                <span class="dot size-1.5 {{ $tag->color->dot() }}"></span>
                                                {{ $tag->name }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                    @error('due_date') <p class="field-error">{{ $message }}</p> @enderror
                </form>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                    <div class="tile flex items-center gap-1 p-1">
                        @foreach ([
                            ['open', __('app.todos.filter_open'), $openCount],
                            ['done', __('app.todos.filter_done'), $doneCount],
                            ['all', __('app.todos.filter_all'), $openCount + $doneCount],
                        ] as [$key, $tabLabel, $count])
                            <a href="{{ route('todos.index', $key === 'open' ? [] : ['filter' => $key]) }}"
                               @class([
                                   'rounded-[var(--radius-control)] px-3 py-1.5 text-xs font-semibold transition',
                                   'bg-hover text-ink' => $filter === $key,
                                   'text-muted hover:text-ink' => $filter !== $key,
                               ])>
                                {{ $tabLabel }}
                                <span class="metric ml-1 {{ $filter === $key ? 'text-accent-text' : 'text-dim' }}" data-count="{{ $key }}">{{ $count }}</span>
                            </a>
                        @endforeach
                    </div>

                    @if ($doneCount > 0)
                        <form method="POST" action="{{ route('todos.clear') }}" data-confirm="{{ __('app.todos.confirm_clear') }}" data-live>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost text-xs">
                                <x-icon name="trash" class="size-3.5"/>
                                {{ __('app.todos.clear_done') }}
                            </button>
                        </form>
                    @endif
                </div>
            </x-card>

            @forelse ($groups as $stateValue => $todos)
                @php $state = \App\Enums\DueState::from($stateValue); @endphp

                <section class="space-y-2" data-group>
                    <div class="flex items-center gap-2 px-1">
                        <h2 class="heading {{ $state->headingClass() }}">{{ $state->label() }}</h2>
                        <span class="metric text-xs text-dim" data-group-count>{{ $todos->count() }}</span>
                    </div>

                    @foreach ($todos as $todo)
                        <x-todo-row :todo="$todo"/>
                    @endforeach
                </section>
            @empty
                <x-card class="text-center">
                    <p class="py-8 text-sm text-faint">
                        {{ $filter === 'done' ? __('app.todos.empty_done') : __('app.todos.empty') }}
                    </p>
                </x-card>
            @endforelse
        </div>

        <div class="lg:col-span-2">
            <x-card class="rise lg:sticky lg:top-8">
                <h2 class="heading">{{ __('app.todos.legend_title') }}</h2>

                <ul class="mt-4 space-y-3 text-xs">
                    @foreach (\App\Enums\DueState::groups() as $state)
                        <li class="flex items-start gap-2.5">
                            <span class="dot mt-1 size-2 shrink-0 {{ $state->dotClass() }}"></span>
                            <span>
                                <span class="block font-semibold text-ink">{{ $state->label() }}</span>
                                <span class="block text-faint">{{ __('app.due.'.$state->value.'_hint') }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-5 border-t border-line pt-4">
                    <p class="text-xs leading-relaxed text-faint">{{ __('app.todos.tag_hint') }}</p>
                    <a href="{{ route('tags.index') }}" class="btn btn-ghost mt-3 w-full">
                        <x-icon name="tag" class="size-4"/>
                        {{ __('app.tags.manage') }}
                    </a>

                    <a href="{{ route('templates') }}" class="btn btn-ghost mt-2 w-full">
                        <x-icon name="list-check" class="size-4"/>
                        {{ __('app.templates.title') }}
                    </a>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
