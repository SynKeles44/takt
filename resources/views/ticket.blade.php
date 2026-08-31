@use('App\Support\Duration')
@use('App\Enums\TicketColumn')

@php
    $local = $file['local'];
    $issue = $file['issue'];
    $running = app(App\Services\TimeTracker::class)->running();
    $isRunning = $running !== null && $local !== null && $running->ticket_id === $local->getKey();
@endphp

<x-app-layout :title="$file['key']" :wide="true">
    <x-card class="rise">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="metric text-sm font-semibold text-accent-text">{{ $file['key'] }}</span>

                    @if ($issue !== null)
                        <span @class([
                                'pill text-[10px]',
                                'border-work/30 bg-work/10 text-work-text' => ($issue['state_type'] ?? '') === 'completed',
                                'border-accent/30 bg-accent/10 text-accent-text' => ($issue['state_type'] ?? '') === 'started',
                            ])>{{ $issue['state'] }}</span>
                    @elseif ($local?->isLocal())
                        <span class="pill text-[10px] text-dim">{{ __('app.ticket.local') }}</span>
                    @endif

                    @if ($local?->column !== null)
                        <span class="pill text-[10px]">{{ $local->column->label() }}</span>
                    @endif

                    @foreach ([$issue['priority'] ?? null, $issue['team'] ?? null, $issue['assignee'] ?? null] as $fact)
                        @if ($fact !== null)
                            <span class="pill text-[10px] text-faint">{{ $fact }}</span>
                        @endif
                    @endforeach
                </div>

                <h2 class="mt-1.5 text-base leading-snug font-semibold text-ink">
                    {{ $file['title'] ?: __('app.ticket.not_found', ['id' => $file['key']]) }}
                </h2>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <form method="POST" action="{{ route('tickets.timer', ['key' => $file['key']]) }}">
                    @csrf
                    <button type="submit" @class(['btn text-xs', 'btn-primary' => ! $isRunning, 'btn-ghost' => $isRunning])>
                        <x-icon :name="$isRunning ? 'stop' : 'play'" class="size-3.5"/>
                        {{ $isRunning ? __('app.ticket.timer_stop') : __('app.ticket.timer_start') }}
                    </button>
                </form>

                <form method="POST" action="{{ route('tickets.focus', ['key' => $file['key']]) }}">
                    @csrf
                    <button type="submit" class="btn btn-ghost text-xs">
                        <x-icon name="check" class="size-3.5"/>
                        {{ $local?->focused_at !== null ? __('app.ticket.unfocus') : __('app.ticket.focus') }}
                    </button>
                </form>

                @if (($issue['url'] ?? $local?->promoted_url) !== null)
                    <a href="{{ $issue['url'] ?? $local->promoted_url }}" target="_blank" class="btn btn-ghost text-xs">
                        <x-icon name="external" class="size-3.5"/>
                        {{ __('app.ticket.open_in_linear') }}
                    </a>
                @endif

                <a href="{{ route('tickets') }}" class="btn btn-ghost text-xs">
                    <x-icon name="arrow-left" class="size-3.5"/>
                    {{ __('app.ticket.back') }}
                </a>
            </div>
        </div>

        @if ($file['contradiction'] !== null)
            <p class="mt-3 flex items-start gap-2 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                <x-icon name="alert" class="mt-0.5 size-3.5 shrink-0"/>
                <span>{{ $file['contradiction'] }}</span>
            </p>
        @endif

        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-line pt-3">
            @foreach (TicketColumn::board() as $column)
                <form method="POST" action="{{ route('tickets.place') }}">
                    @csrf
                    <input type="hidden" name="key" value="{{ $file['key'] }}">
                    <input type="hidden" name="spalte" value="{{ $column->value }}">
                    <button type="submit" @class(['segment text-[11px]', 'segment-active' => $local?->column === $column])>
                        {{ $column->label() }}
                    </button>
                </form>
            @endforeach

            @if ($local?->column !== null)
                <form method="POST" action="{{ route('tickets.place') }}">
                    @csrf
                    <input type="hidden" name="key" value="{{ $file['key'] }}">
                    <input type="hidden" name="spalte" value="">
                    <button type="submit" class="segment text-[11px] text-faint">{{ __('app.ticket.off_board') }}</button>
                </form>
            @endif
        </div>
    </x-card>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            <x-card>
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="heading">{{ __('app.ticket.booked') }}</h2>

                    <span class="flex items-baseline gap-2">
                        <span class="metric text-xl font-semibold text-work-text">{{ Duration::human($file['booked']) }}</span>
                        @if ($file['estimate'] !== null)
                            <span class="metric text-xs text-faint">/ {{ Duration::human($file['estimate']) }}</span>
                        @endif
                    </span>
                </div>

                @if ($file['entries']->isEmpty())
                    <p class="mt-3 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                        {{ __('app.ticket.no_time') }}
                    </p>
                @else
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($file['entries']->take(20) as $entry)
                            <li class="row flex items-center gap-3 px-3 py-2">
                                <span class="metric shrink-0 text-[11px] text-dim">{{ $entry->started_at->isoFormat('D. MMM, HH:mm') }}</span>
                                <span class="min-w-0 flex-1 text-xs text-muted">{{ $entry->type->label() }}</span>
                                <span class="metric shrink-0 text-xs text-work-text">{{ Duration::human($entry->durationInSeconds()) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card>
                <h2 class="heading">{{ __('app.ticket.timeline') }}</h2>

                @if ($file['timeline']->isEmpty())
                    <p class="mt-3 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                        {{ __('app.ticket.timeline_empty') }}
                    </p>
                @else
                    <ol class="ticket-timeline mt-3">
                        @foreach ($file['timeline']->take(60) as $event)
                            <li class="ticket-event" data-kind="{{ $event['kind'] }}">
                                <span class="ticket-event-dot" aria-hidden="true"></span>

                                <div class="min-w-0">
                                    <p class="flex flex-wrap items-center gap-2">
                                        <span class="pill text-[9px]">{{ __('app.ticket.kind.'.$event['kind']) }}</span>
                                        <span class="metric text-[10px] text-faint">{{ $event['at']->isoFormat('D. MMM YY, HH:mm') }}</span>
                                    </p>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-ink">{{ $event['title'] }}</p>
                                    @if ($event['meta'] !== '')
                                        <p class="text-[10px] text-faint">{{ $event['meta'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            @if ($issue !== null)
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.linear_fields') }}</h2>

                    <form method="POST" action="{{ route('tickets.linear', ['key' => $file['key']]) }}" class="mt-3 space-y-3">
                        @csrf
                        <input type="hidden" name="aktion" value="felder">

                        <label class="block">
                            <span class="label">{{ __('app.ticket.new_title') }}</span>
                            <input type="text" name="titel" value="{{ $issue['title'] ?? '' }}" maxlength="200" class="control mt-1 w-full text-sm">
                        </label>

                        <div class="flex flex-wrap items-end gap-2">
                            <label>
                                <span class="label">{{ __('app.ticket.linear_state') }}</span>
                                <input type="text" name="status" value="{{ $issue['state'] ?? '' }}" maxlength="60" class="control mt-1 w-40 text-sm">
                            </label>

                            <label>
                                <span class="label">{{ __('app.ticket.linear_priority') }}</span>
                                <select name="prio" class="control mt-1 text-sm">
                                    <option value="">—</option>
                                    @foreach ([1 => 'Urgent', 2 => 'High', 3 => 'Medium', 4 => 'Low', 0 => 'None'] as $value => $label)
                                        <option value="{{ $value }}" @selected(($issue['priority'] ?? '') === $label)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <button type="submit" class="btn btn-primary text-xs">
                                <x-icon name="check" class="size-3.5"/>
                                {{ __('app.ticket.saved') }}
                            </button>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('tickets.linear', ['key' => $file['key']]) }}" class="mt-4 space-y-2 border-t border-line pt-4">
                        @csrf
                        <input type="hidden" name="aktion" value="kommentar">

                        <label class="block">
                            <span class="label">{{ __('app.ticket.linear_comment') }}</span>
                            <textarea name="kommentar" rows="3" class="control mt-1 w-full text-sm" maxlength="10000"></textarea>
                        </label>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="submit" class="btn btn-ghost text-xs">
                                <x-icon name="send" class="size-3.5"/>
                                {{ __('app.ticket.linear_send') }}
                            </button>
                        </div>
                    </form>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                        @foreach (['zuweisen' => __('app.ticket.assign_me'), 'abgeben' => __('app.ticket.unassign')] as $action => $label)
                            <form method="POST" action="{{ route('tickets.linear', ['key' => $file['key']]) }}">
                                @csrf
                                <input type="hidden" name="aktion" value="{{ $action }}">
                                <button type="submit" class="btn btn-ghost text-xs">{{ $label }}</button>
                            </form>
                        @endforeach
                    </div>
                </x-card>
            @elseif ($local?->isLocal())
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.local') }}</h2>

                    <form method="POST" action="{{ route('tickets.update', ['key' => $file['key']]) }}" class="mt-3 space-y-3">
                        @csrf
                        <label class="block">
                            <span class="label">{{ __('app.ticket.new_title') }}</span>
                            <input type="text" name="titel" value="{{ $local->title }}" maxlength="200" class="control mt-1 w-full text-sm">
                        </label>

                        <label class="block">
                            <span class="label">{{ __('app.ticket.new_body') }}</span>
                            <textarea name="beschreibung" rows="5" class="control mt-1 w-full text-sm">{{ $local->body }}</textarea>
                        </label>

                        <button type="submit" class="btn btn-primary text-xs">
                            <x-icon name="check" class="size-3.5"/>
                            {{ __('app.ticket.saved') }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('tickets.linear', ['key' => $file['key']]) }}" class="mt-4 border-t border-line pt-4">
                        @csrf
                        <input type="hidden" name="aktion" value="anlegen">
                        <button type="submit" class="btn btn-ghost text-xs">
                            <x-icon name="external" class="size-3.5"/>
                            {{ __('app.ticket.promote') }}
                        </button>
                    </form>
                </x-card>
            @endif
        </div>

        <div class="space-y-5">
            <x-card>
                <h2 class="heading">{{ __('app.ticket.notes') }}</h2>
                <p class="mt-0.5 text-[11px] text-faint">{{ __('app.ticket.notes_hint') }}</p>

                <form method="POST" action="{{ route('tickets.update', ['key' => $file['key']]) }}" class="mt-3 space-y-3">
                    @csrf
                    <textarea name="notizen" rows="8" class="control w-full text-sm"
                              placeholder="{{ __('app.ticket.notes_placeholder') }}">{{ $local?->notes }}</textarea>

                    <div class="flex flex-wrap items-end gap-2">
                        <label class="flex-1">
                            <span class="label">{{ __('app.ticket.estimate') }}</span>
                            <input type="text" name="schaetzung" class="control mt-1 w-full text-sm"
                                   value="{{ $file['estimate'] !== null ? Duration::compact($file['estimate']) : '' }}"
                                   placeholder="{{ __('app.ticket.estimate_placeholder') }}">
                        </label>

                        <button type="submit" class="btn btn-primary text-xs">
                            <x-icon name="check" class="size-3.5"/>
                            {{ __('app.ticket.saved') }}
                        </button>
                    </div>
                </form>

                @if ($local?->column === TicketColumn::Waiting)
                    <form method="POST" action="{{ route('tickets.update', ['key' => $file['key']]) }}" class="mt-4 space-y-2 border-t border-line pt-4">
                        @csrf
                        <label class="block">
                            <span class="label">{{ __('app.ticket.waiting_reason') }}</span>
                            <input type="text" name="grund" value="{{ $local->waiting_reason }}" maxlength="120"
                                   class="control mt-1 w-full text-sm" placeholder="{{ __('app.ticket.waiting_placeholder') }}">
                        </label>
                        <button type="submit" class="btn btn-ghost text-xs">{{ __('app.ticket.saved') }}</button>
                    </form>
                @endif
            </x-card>

            @if ($file['pulls'] !== [])
                <x-card>
                    <h2 class="heading">{{ trans_choice('app.tickets.pulls', count($file['pulls'])) }}</h2>
                    <div class="mt-3">
                        <x-pull-list :pulls="$file['pulls']" :compact="true"/>
                    </div>
                </x-card>
            @endif

            @if ($file['branches']->isNotEmpty())
                <x-card>
                    <h2 class="heading">{{ trans_choice('app.tickets.branches', $file['branches']->count()) }}</h2>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($file['branches']->take(12) as $branch)
                            <span class="pill metric text-[10px]">{{ $branch['name'] }}</span>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($file['notes']->isNotEmpty())
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.notes_found') }}</h2>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($file['notes'] as $note)
                            <li class="row px-3 py-2">
                                <span class="metric text-[10px] text-faint">{{ $note->day->isoFormat('D. MMM YY') }}</span>
                                <p class="mt-0.5 line-clamp-3 text-xs text-muted">{{ $note->body }}</p>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

            @if ($file['absences']->isNotEmpty())
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.absences') }}</h2>
                    <ul class="mt-3 space-y-1.5">
                        @foreach ($file['absences']->take(8) as $absence)
                            <li class="row flex items-center gap-3 px-3 py-2">
                                <span class="pill shrink-0 text-[9px] {{ $absence->type->pillClasses() }}">{{ $absence->type->label() }}</span>
                                <span class="metric text-[10px] text-faint">
                                    {{ $absence->starts_on->isoFormat('D. MMM') }} – {{ $absence->ends_on->isoFormat('D. MMM') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
</x-app-layout>
