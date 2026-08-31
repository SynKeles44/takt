@props(['summary', 'windows'])

{{--
    The period the statistic reads: three day windows and a range of one's own. Both places
    that show home office use this, so the choice reads and behaves the same in either.
--}}
<div class="mt-3" data-period>
    <form method="POST" action="{{ route('home-office.range') }}" class="segmented" data-live>
        @csrf
        @foreach ($windows as $option)
            <button type="submit" name="window" value="{{ $option }}"
                    @class(['segment', 'segment-active' => ! $summary['custom'] && $option === $summary['window']])>
                {{ __('app.widget.home_office.window_'.$option) }}
            </button>
        @endforeach

        <button type="button" data-period-toggle
                @class(['segment', 'segment-active' => $summary['custom']])>
            {{ __('app.absence.home_office_custom') }}
        </button>
    </form>

    <form method="POST" action="{{ route('home-office.range') }}"
          {{-- the button sits on its own row: in a two-column widget a third column gets cut off --}}
          @class(['mt-2 grid grid-cols-2 gap-2', 'hidden' => ! $summary['custom']])
          data-period-fields data-live>
        @csrf

        <label class="block">
            <span class="label">{{ __('app.absence.from') }}</span>
            <input type="date" name="from" value="{{ $summary['from']->toDateString() }}" class="control metric text-xs" required>
        </label>

        <label class="block">
            <span class="label">{{ __('app.absence.to') }}</span>
            <input type="date" name="to" value="{{ $summary['to']->toDateString() }}" class="control metric text-xs" required>
        </label>

        <button type="submit" class="btn btn-ghost col-span-2 text-xs">
            <x-icon name="check" class="size-3.5"/>
            {{ __('app.absence.home_office_apply') }}
        </button>
    </form>

    @error('to') <p class="field-error">{{ $message }}</p> @enderror
    @error('from') <p class="field-error">{{ $message }}</p> @enderror
</div>
