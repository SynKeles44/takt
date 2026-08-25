@props(['tag'])

<span {{ $attributes->class(['pill', $tag->color->classes()]) }}>
    <span class="dot size-1.5 {{ $tag->color->dot() }}"></span>
    {{ $tag->name }}
</span>
