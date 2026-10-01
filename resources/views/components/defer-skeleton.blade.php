@props(['cards' => 3, 'rows' => 4])

{{--
    The page while its slow half is still being fetched.

    Card-shaped rather than a single grey block, because the point of a skeleton is that the layout
    does not move when the content lands — a placeholder with the wrong geometry is a spinner that
    also causes a jump.
--}}
<div {{ $attributes->class('stack mt-5') }} aria-hidden="true">
    @foreach (range(1, max(1, (int) $cards)) as $card)
        <x-card style="opacity: {{ number_format(1 - ($card - 1) * 0.22, 2, '.', '') }}">
            <div class="skeleton skeleton-line w-32"></div>
            <x-skeleton class="mt-3" type="row" :count="$rows"/>
        </x-card>
    @endforeach
</div>
