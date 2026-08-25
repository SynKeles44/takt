@props(['colors', 'selected' => null, 'name' => 'color'])

<div class="flex flex-wrap items-center gap-2">
    @foreach ($colors as $color)
        <label class="cursor-pointer" title="{{ $color->label() }}">
            <input type="radio" name="{{ $name }}" value="{{ $color->value }}" class="peer sr-only"
                   @checked($selected === $color || (! $selected && $loop->first))>
            <span class="grid size-8 place-items-center rounded-[var(--radius-control)] border border-line transition
                         peer-checked:border-accent peer-checked:ring-2 peer-checked:ring-accent/30
                         peer-focus-visible:ring-2 peer-focus-visible:ring-accent/50">
                <span class="dot size-4 {{ $color->dot() }}"></span>
            </span>
            <span class="sr-only">{{ $color->label() }}</span>
        </label>
    @endforeach
</div>
