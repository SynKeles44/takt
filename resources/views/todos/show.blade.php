<x-app-layout :title="$todo->title">
    <a href="{{ route('todos.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.todos.back') }}
    </a>

    <div class="grid gap-5 lg:grid-cols-2">
        <x-card class="rise">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                @unless ($todo->isDone())
                    <x-due-badge :todo="$todo"/>
                @endunless

                @foreach ($todo->tags as $tag)
                    <x-tag-badge :tag="$tag"/>
                @endforeach

                @if ($todo->recurrence->repeats())
                    <span class="pill">
                        <x-icon name="repeat" class="size-3.5"/>
                        {{ $todo->recurrence->label() }}
                    </span>
                @endif
                </div>

                <a href="{{ route('todos.edit', $todo) }}" class="btn btn-ghost shrink-0 text-xs">
                    <x-icon name="pencil" class="size-3.5"/>
                    {{ __('app.todos.edit_action') }}
                </a>
            </div>

            @if ($todo->body)
                <p class="mt-4 whitespace-pre-line text-sm leading-relaxed text-muted">{{ $todo->body }}</p>
            @else
                <p class="mt-4 text-sm text-dim">{{ __('app.todos.no_details') }}</p>
            @endif
        </x-card>

        <x-card class="rise flex flex-col gap-4 self-start">
            <div class="space-y-3 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-faint">{{ __('app.todos.state') }}</span>
                    <span class="font-semibold {{ $todo->isDone() ? 'text-work-text' : 'text-ink' }}">
                        {{ $todo->isDone() ? __('app.todos.filter_done') : $todo->dueState()->label() }}
                    </span>
                </div>

                @if ($todo->due_at)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-faint">{{ __('app.todos.due_date') }}</span>
                        <span class="metric text-ink">{{ $todo->due_at->isoFormat($todo->due_has_time ? 'dd, D. MMM YYYY HH:mm' : 'dd, D. MMM YYYY') }}</span>
                    </div>
                @endif

                @if ($todo->recurrence->repeats())
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-faint">{{ __('app.todos.recurrence_field') }}</span>
                        <span class="font-semibold text-accent-text">{{ $todo->recurrence->label() }}</span>
                    </div>
                @endif

                @if ($todo->warnLeadMinutes() > 0)
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-faint">{{ __('app.todos.warn_from') }}</span>
                        <span class="metric text-rest-text">{{ \App\Support\Duration::human($todo->warnLeadMinutes() * 60) }}</span>
                    </div>
                @endif

                @if ($todo->autoCompletes())
                    <p class="rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                        {{ __('app.todos.auto_complete_note') }}
                    </p>
                @endif


                <div class="flex items-center justify-between gap-3">
                    <span class="text-faint">{{ __('app.todos.created') }}</span>
                    <span class="metric text-muted">{{ $todo->created_at->isoFormat('D. MMM YYYY') }}</span>
                </div>
            </div>

            @if ($todo->due_at !== null)
                <div class="flex gap-2">
                    @foreach ([['hour', __('app.todos.snooze_hour')], ['tomorrow', __('app.todos.snooze_day')], ['week', __('app.todos.snooze_week')]] as [$by, $snoozeLabel])
                        <form method="POST" action="{{ route('todos.snooze', $todo) }}" class="flex-1" data-live>
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="by" value="{{ $by }}">
                            <button type="submit" class="btn btn-ghost w-full text-xs">{{ $snoozeLabel }}</button>
                        </form>
                    @endforeach
                </div>
            @endif

            <div class="flex gap-2">
                <form method="POST" action="{{ route('todos.toggle', $todo) }}" class="flex-1" data-live>
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-ghost w-full">
                        <x-icon name="check" class="size-4"/>
                        {{ $todo->isDone() ? __('app.todos.reopen') : __('app.todos.complete') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('todos.destroy', $todo) }}" data-confirm="{{ __('app.todos.confirm_delete') }}" data-live>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <x-icon name="trash" class="size-4"/>
                        {{ __('app.form.delete') }}
                    </button>
                </form>
            </div>
        </x-card>

        <x-card class="rise lg:col-span-2">
            <div class="grid stack-grid lg:grid-cols-2">
                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.todos.steps_field') }}</h2>
                        @php $progress = $todo->stepProgress(); @endphp
                        @if ($progress)
                            <span class="pill metric">{{ $progress['done'] }}/{{ $progress['total'] }}</span>
                        @endif
                    </div>

                    @if ($progress)
                        <div class="mt-3 h-1.5 overflow-hidden rounded-[var(--radius-pill)] bg-hover">
                            <div class="h-full rounded-[var(--radius-pill)] bg-gradient-to-r from-work to-work-2 transition-[width] duration-500"
                                 style="width: {{ $progress['percent'] }}%"></div>
                        </div>
                    @endif

                    <div class="mt-4 space-y-2">
                        @forelse ($todo->steps as $step)
                            <div class="row flex items-center gap-3" data-item data-done="{{ $step->isDone() ? 1 : 0 }}" data-stay>
                                <form method="POST" action="{{ route('steps.toggle', [$todo, $step]) }}" class="shrink-0" data-async>
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="check" aria-label="{{ __('app.todos.complete') }}">
                                        <x-icon name="check" class="size-3"/>
                                    </button>
                                </form>

                                <span class="min-w-0 flex-1 truncate text-sm text-ink" data-done-strike>
                                    {{ $step->title }}
                                </span>

                                <form method="POST" action="{{ route('steps.destroy', [$todo, $step]) }}" class="shrink-0" data-live>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                                        <x-icon name="trash" class="size-3.5"/>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-4 text-center text-xs text-faint">
                                {{ __('app.todos.steps_empty') }}
                            </p>
                        @endforelse
                    </div>

                    @if ($templates->isNotEmpty())
                        <form method="POST" action="{{ route('templates.apply', $todo) }}" class="mt-3 flex items-center gap-2" data-live>
                            @csrf
                            <select name="step_template_id" class="control min-w-0 flex-1 text-xs" aria-label="{{ __('app.templates.apply') }}">
                                @foreach ($templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }} · {{ trans_choice('app.templates.count', $template->items->count()) }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-ghost shrink-0 text-xs">{{ __('app.templates.apply') }}</button>
                        </form>
                    @endif

                    @if ($todo->steps->isNotEmpty())
                        <form method="POST" action="{{ route('templates.from-todo', $todo) }}" class="mt-2 flex items-center gap-2" data-live>
                            @csrf
                            <input type="text" name="name" class="control min-w-0 flex-1 text-xs" maxlength="80"
                                   placeholder="{{ __('app.templates.save_as_placeholder') }}" required>
                            <button type="submit" class="btn btn-ghost shrink-0 text-xs">{{ __('app.templates.save_as') }}</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('steps.store', $todo) }}" class="mt-3 flex items-center gap-2" data-live>
                        @csrf
                        <input type="text" name="title" class="control min-w-0 flex-1"
                               placeholder="{{ __('app.todos.step_placeholder') }}" maxlength="200" required>
                        <button type="submit" class="btn btn-ghost shrink-0"
                                aria-label="{{ __('app.todos.step_add') }}" title="{{ __('app.todos.step_add') }}">
                            <x-icon name="plus" class="size-4"/>
                        </button>
                    </form>
                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <h2 class="heading">{{ __('app.todos.attachments_field') }}</h2>

                    <div class="mt-4 space-y-2">
                        @forelse ($todo->attachments as $attachment)
                            <div class="row flex items-center gap-3">
                                <span class="shrink-0 text-muted"><x-icon name="paperclip" class="size-4"/></span>

                                <a href="{{ route('attachments.show', [$todo, $attachment]) }}"
                                   class="min-w-0 flex-1 truncate text-sm text-ink hover:underline">{{ $attachment->name }}</a>

                                <span class="metric shrink-0 text-xs text-dim">{{ $attachment->humanSize() }}</span>

                                <form method="POST" action="{{ route('attachments.destroy', [$todo, $attachment]) }}" class="shrink-0"
                                      data-confirm="{{ __('app.todos.confirm_delete_attachment') }}" data-live>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                                        <x-icon name="trash" class="size-3.5"/>
                                    </button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-4 text-center text-xs text-faint">
                                {{ __('app.todos.attachments_empty') }}
                            </p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('attachments.store', $todo) }}" enctype="multipart/form-data" class="mt-3 space-y-2" data-live>
                        @csrf
                        <input type="file" name="file" required aria-label="{{ __('app.todos.attachment_add') }}"
                                   class="control file:mr-3 file:rounded-[var(--radius-pill)] file:border-0 file:bg-hover file:px-3 file:py-1 file:text-xs file:text-ink">
                        <button type="submit" class="btn btn-ghost w-full">
                            <x-icon name="paperclip" class="size-4"/>
                            {{ __('app.todos.attachment_add') }}
                        </button>
                        <p class="text-xs text-faint">{{ __('app.todos.attachment_hint') }}</p>
                        @error('file') <p class="field-error">{{ $message }}</p> @enderror
                    </form>
                </div>
            </div>
        </x-card>
    </div>
</x-app-layout>
