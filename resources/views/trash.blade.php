@use('App\Support\Duration')

<x-app-layout :title="__('app.trash.title')">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-ink">{{ __('app.trash.title') }}</h2>
                <p class="mt-0.5 text-xs text-muted">{{ __('app.trash.intro', ['days' => $keepDays]) }}</p>
            </div>

            @if ($entries->isNotEmpty() || $todos->isNotEmpty())
                <form method="POST" action="{{ route('trash.empty') }}" data-confirm="{{ __('app.trash.confirm_empty') }}" data-live>
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger text-xs">
                        <x-icon name="trash" class="size-3.5"/>
                        {{ __('app.trash.empty_action') }}
                    </button>
                </form>
            @endif
        </div>
    </x-card>

    <div class="stack-grid mt-5 grid lg:grid-cols-2">
        <x-card>
            <h2 class="heading">{{ __('app.nav.history') }}</h2>

            <div class="mt-4 space-y-2">
                @forelse ($entries as $entry)
                    <div class="row flex flex-wrap items-center gap-2">
                        <x-type-badge :type="$entry->type" class="shrink-0"/>

                        <span class="metric text-sm text-ink">
                            {{ $entry->started_at->isoFormat('dd, D. MMM') }} · {{ $entry->started_at->format('H:i') }}–{{ $entry->ended_at?->format('H:i') ?? '···' }}
                        </span>

                        <span class="metric ml-auto text-xs text-dim">
                            {{ __('app.trash.deleted_at', ['when' => $entry->deleted_at->isoFormat('D. MMM, HH:mm')]) }}
                        </span>

                        <div class="flex shrink-0 items-center gap-1">
                            <form method="POST" action="{{ route('trash.entry.restore', $entry) }}" data-live>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="icon-action" aria-label="{{ __('app.trash.restore') }}" title="{{ __('app.trash.restore') }}">
                                    <x-icon name="repeat" class="size-4"/>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('trash.entry.purge', $entry) }}" data-confirm="{{ __('app.trash.confirm_purge') }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.trash.purge') }}" title="{{ __('app.trash.purge') }}">
                                    <x-icon name="trash" class="size-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-5 text-center text-xs text-faint">
                        {{ __('app.trash.empty_entries') }}
                    </p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h2 class="heading">{{ __('app.nav.todos') }}</h2>

            <div class="mt-4 space-y-2">
                @forelse ($todos as $todo)
                    <div class="row flex flex-wrap items-center gap-2">
                        <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $todo->title }}</span>

                        @foreach ($todo->tags as $tag)
                            <x-tag-badge :tag="$tag"/>
                        @endforeach

                        <span class="metric text-xs text-dim">
                            {{ __('app.trash.deleted_at', ['when' => $todo->deleted_at->isoFormat('D. MMM, HH:mm')]) }}
                        </span>

                        <div class="flex shrink-0 items-center gap-1">
                            <form method="POST" action="{{ route('trash.todo.restore', $todo) }}" data-live>
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="icon-action" aria-label="{{ __('app.trash.restore') }}" title="{{ __('app.trash.restore') }}">
                                    <x-icon name="repeat" class="size-4"/>
                                </button>
                            </form>

                            <form method="POST" action="{{ route('trash.todo.purge', $todo) }}" data-confirm="{{ __('app.trash.confirm_purge') }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.trash.purge') }}" title="{{ __('app.trash.purge') }}">
                                    <x-icon name="trash" class="size-4"/>
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-5 text-center text-xs text-faint">
                        {{ __('app.trash.empty_todos') }}
                    </p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
