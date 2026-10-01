@use('App\Support\Duration')

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

        {{-- the segment classes were here all along; what was missing is the row that carries the marker --}}
    </x-card>

    <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="space-y-5">
            @if (($issue['description'] ?? null) !== null)
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.description') }}</h2>

                    {{--
                        Linear's description is Markdown, and it is shown as the text it is rather
                        than rendered. Rendering it means either a Markdown library in the request
                        path or a hand-written subset, and a hand-written subset of Markdown that
                        emits HTML is an XSS surface for text this app does not own. Pre-wrapped
                        text is readable, keeps the author's line breaks, and cannot execute.
                    --}}
                    <p class="mt-3 max-h-96 overflow-y-auto whitespace-pre-wrap text-sm leading-relaxed text-muted">{{ $issue['description'] }}</p>
                </x-card>
            @endif

            @if (($issue['children'] ?? []) !== [])
                <x-card>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.ticket.children') }}</h2>
                        <span class="pill text-[10px]">{{ count($issue['children']) }}</span>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        @foreach ($issue['children'] as $child)
                            <a href="{{ route('tickets.show', ['key' => $child['id']]) }}" class="row flex items-center gap-3 px-3 py-2">
                                <span @class([
                                        'size-2 shrink-0 rounded-full',
                                        'bg-work' => $child['state_type'] === 'completed',
                                        'bg-accent' => $child['state_type'] === 'started',
                                        'bg-line-strong' => ! in_array($child['state_type'], ['completed', 'started'], true),
                                     ])></span>
                                <span class="metric shrink-0 text-[11px] text-dim">{{ $child['id'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $child['title'] }}</span>
                                <span class="shrink-0 text-[11px] text-faint">{{ $child['state'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if (($issue['comments'] ?? []) !== [])
                <x-card>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="heading">{{ __('app.ticket.comments') }}</h2>
                        <span class="pill text-[10px]">{{ count($issue['comments']) }}</span>
                    </div>

                    {{--
                        A colour per author, derived from the name. A thread of twelve comments
                        between three people is a wall of identical grey boxes until the eye has
                        something to sort it by, and the name alone is at the top of each box where
                        it has to be read rather than seen.
                    --}}
                    <ul class="mt-3 space-y-3">
                        @foreach ($issue['comments'] as $comment)
                            @php
                                $author = $comment['author'] ?? __('app.ticket.unknown_author');
                                $colour = \App\Support\Palette::forName($author);
                            @endphp

                            <li class="comment flex gap-2.5" style="--author: {{ $colour['background'] }}">
                                <x-avatar :name="$author"/>

                                <div class="comment-body min-w-0 flex-1">
                                    <p class="flex items-baseline gap-2 text-[11px]">
                                        <span class="font-semibold text-ink">{{ $author }}</span>
                                        @if ($comment['at'] !== '')
                                            <span class="metric text-faint">{{ \Illuminate\Support\Carbon::parse($comment['at'])->isoFormat('D. MMM, HH:mm') }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-1.5 whitespace-pre-wrap text-sm leading-relaxed text-muted">{{ $comment['body'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif

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
            @if ($issue !== null)
                <x-card>
                    <h2 class="heading">{{ __('app.ticket.properties') }}</h2>

                    <dl class="mt-3 space-y-2 text-xs">
                        @foreach ([
                            [__('app.ticket.prop_project'), $issue['project'] ?? null],
                            [__('app.ticket.prop_sprint'), ($issue['cycle']['name'] ?? null) ?: (($issue['cycle']['number'] ?? null) !== null ? __('app.sprint.number', ['number' => $issue['cycle']['number']]) : null)],
                            [__('app.ticket.prop_points'), ($issue['estimate'] ?? null) !== null ? rtrim(rtrim(number_format((float) $issue['estimate'], 1, ',', ''), '0'), ',') : null],
                            [__('app.ticket.prop_priority'), $issue['priority'] ?? null],
                            [__('app.ticket.prop_due'), $issue['due_on'] ?? null],
                            [__('app.ticket.prop_assignee'), $issue['assignee'] ?? null],
                            [__('app.ticket.prop_creator'), $issue['creator'] ?? null],
                            [__('app.ticket.prop_team'), $issue['team'] ?? null],
                        ] as [$label, $value])
                            @if ($value !== null && $value !== '')
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-faint">{{ $label }}</dt>
                                    <dd class="min-w-0 truncate text-end text-ink">{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>

                    @if (($issue['labels'] ?? []) !== [])
                        <div class="mt-3 flex flex-wrap gap-1 border-t border-line pt-3">
                            @foreach ($issue['labels'] as $label)
                                <span class="pill text-[10px] text-dim">
                                    <span class="size-1.5 rounded-full" style="background: {{ $label['color'] ?? 'var(--color-accent)' }}"></span>
                                    {{ $label['name'] }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if (($issue['parent'] ?? null) !== null)
                        <a href="{{ route('tickets.show', ['key' => $issue['parent']['id']]) }}"
                           class="row mt-3 flex items-center gap-2 px-3 py-2">
                            <x-icon name="chevron-up" class="size-3.5 shrink-0 text-dim"/>
                            <span class="metric shrink-0 text-[11px] text-dim">{{ $issue['parent']['id'] }}</span>
                            <span class="min-w-0 flex-1 truncate text-xs text-ink">{{ $issue['parent']['title'] }}</span>
                        </a>
                    @endif

                    {{--
                        Copy, because the three things a ticket is reached by live in three other
                        programs: the key goes in a commit message, the branch name in a terminal,
                        the link in a chat. Linear puts these behind a menu for the same reason.
                    --}}
                    {{--
                        Duplicating creates a real issue in Linear that the team will see, so it
                        asks first. Everything above it only writes to the clipboard.
                    --}}
                    <form method="POST" action="{{ route('tickets.linear', ['key' => $file['key']]) }}"
                          class="mt-3 border-t border-line pt-3" data-confirm="{{ __('app.ticket.copy_confirm') }}">
                        @csrf
                        <input type="hidden" name="aktion" value="duplizieren">
                        <button type="submit" class="btn btn-ghost w-full text-xs">
                            <x-icon name="clipboard" class="size-3.5"/>
                            {{ __('app.ticket.duplicate') }}
                        </button>
                    </form>

                    <div class="mt-3 flex flex-wrap gap-1 border-t border-line pt-3">
                        @foreach (array_filter([
                            [__('app.ticket.copy_key'), $file['key']],
                            [__('app.ticket.copy_branch'), $issue['branch'] ?? null],
                            [__('app.ticket.copy_link'), $issue['url'] ?? null],
                            [__('app.ticket.copy_title'), $file['key'].' '.$file['title']],
                        ], fn (array $row): bool => $row[1] !== null) as [$label, $value])
                            <button type="button" class="pill hover:text-ink" data-copy="{{ $value }}"
                                    data-copy-label="{{ __('app.dev.copied') }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </x-card>
            @endif

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
    <x-mascot pose="think" class="mascot-at-tail-right size-14"/>
</x-app-layout>
