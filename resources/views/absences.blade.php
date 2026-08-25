<x-app-layout :title="__('app.absence.title')">
    <a href="{{ route('calendar') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-muted transition hover:text-ink">
        <x-icon name="arrow-left" class="size-4"/>
        {{ __('app.absence.back') }}
    </a>

    <div class="stack-grid grid lg:grid-cols-5">
        <div class="stack lg:col-span-3">
            <x-card class="rise">
                <h2 class="text-base font-semibold text-ink">{{ __('app.absence.new') }}</h2>
                <p class="mt-0.5 text-xs text-muted">{{ __('app.absence.intro') }}</p>

                <form method="POST" action="{{ route('absences.store') }}" class="mt-5 space-y-4" data-live>
                    @csrf

                    <div>
                        <span class="label">{{ __('app.form.type') }}</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach ($types as $type)
                                <label class="block">
                                    <input type="radio" name="type" value="{{ $type->value }}" class="peer sr-only"
                                           @checked(old('type', 'vacation') === $type->value)>
                                    <span class="control flex cursor-pointer items-center justify-center text-center text-xs font-medium text-muted transition peer-checked:border-accent/50 peer-checked:bg-accent/10 peer-checked:text-accent-text">
                                        {{ $type->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('type') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="starts_on" class="label">{{ __('app.absence.from') }}</label>
                            <input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" class="control metric" required>
                            @error('starts_on') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="ends_on" class="label">{{ __('app.absence.to') }}</label>
                            <input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', now()->toDateString()) }}" class="control metric" required>
                            @error('ends_on') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label for="note" class="label">{{ __('app.form.note') }}</label>
                        <input id="note" type="text" name="note" value="{{ old('note') }}" class="control" maxlength="200"
                               placeholder="{{ __('app.absence.note_placeholder') }}">
                    </div>

                    <button type="submit" class="btn btn-primary w-full">
                        <x-icon name="check" class="size-4"/>
                        {{ __('app.absence.save') }}
                    </button>
                </form>
            </x-card>

            <x-card>
                <h2 class="heading">{{ __('app.absence.list') }}</h2>

                <div class="mt-4 space-y-2">
                    @forelse ($absences as $absence)
                        <div class="row flex flex-wrap items-center gap-2">
                            <span class="pill shrink-0 {{ $absence->type->pillClasses() }}">{{ $absence->type->label() }}</span>

                            <span class="metric text-sm text-ink">
                                {{ $absence->starts_on->isoFormat('D. MMM') }}
                                @unless ($absence->starts_on->isSameDay($absence->ends_on))
                                    – {{ $absence->ends_on->isoFormat('D. MMM YYYY') }}
                                @endunless
                            </span>

                            <span class="pill shrink-0">{{ trans_choice('app.absence.workdays', $absence->workdays()) }}</span>

                            @if ($absence->note)
                                <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ $absence->note }}</span>
                            @endif

                            <form method="POST" action="{{ route('absences.destroy', $absence) }}" class="ml-auto shrink-0"
                                  data-confirm="{{ __('app.absence.confirm_delete') }}" data-live>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="icon-action icon-action-danger" aria-label="{{ __('app.form.delete') }}">
                                    <x-icon name="trash" class="size-4"/>
                                </button>
                            </form>
                        </div>
                    @empty
                        <p class="rounded-[var(--radius-control)] border border-dashed border-line px-4 py-5 text-center text-xs text-faint">
                            {{ __('app.absence.empty') }}
                        </p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <div class="stack lg:col-span-2">
            <x-card class="rise">
                <h2 class="heading">{{ __('app.absence.vacation_title') }}</h2>

                <div class="mt-4 grid grid-cols-3 gap-2">
                    @foreach ([
                        ['label' => __('app.absence.entitlement'), 'value' => $vacation['entitlement'], 'tone' => 'text-ink'],
                        ['label' => __('app.absence.taken'), 'value' => $vacation['taken'], 'tone' => 'text-accent-text'],
                        ['label' => __('app.absence.remaining'), 'value' => $vacation['remaining'], 'tone' => $vacation['remaining'] < 0 ? 'text-danger-text' : 'text-work-text'],
                    ] as $tile)
                        <div class="tile px-3 py-2.5 text-center">
                            <p class="text-[11px] text-faint">{{ $tile['label'] }}</p>
                            <p class="metric mt-0.5 text-lg font-bold {{ $tile['tone'] }}">{{ rtrim(rtrim(number_format((float) $tile['value'], 1, ',', ''), '0'), ',') }}</p>
                        </div>
                    @endforeach
                </div>

                <p class="mt-3 text-xs text-faint">{{ __('app.absence.vacation_hint', ['year' => $vacation['year']]) }}</p>
            </x-card>

            <x-card>
                <div class="flex items-center justify-between gap-2">
                    <h2 class="heading">{{ __('app.absence.holidays_title') }}</h2>
                    <span class="pill">{{ $region }}</span>
                </div>

                <ul class="mt-4 space-y-1.5 text-xs">
                    @foreach ($holidays as $date => $name)
                        @php $day = \Illuminate\Support\Carbon::parse($date); @endphp
                        <li class="flex items-center justify-between gap-2 {{ $day->isPast() ? 'text-dim' : 'text-muted' }}">
                            <span class="truncate">{{ $name }}</span>
                            <span class="metric shrink-0">{{ $day->isoFormat('dd, D. MMM') }}</span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-4 text-xs text-faint">{{ __('app.absence.holidays_hint') }}</p>
            </x-card>
        </div>
    </div>
</x-app-layout>
