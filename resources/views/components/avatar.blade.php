@props(['name', 'size' => 'size-7'])

@php $colour = \App\Support\Palette::forName($name); @endphp

<span {{ $attributes->class(['grid shrink-0 place-items-center rounded-full text-[10px] font-bold', $size]) }}
      style="background: {{ $colour['background'] }}; color: {{ $colour['ink'] }}"
      title="{{ $name }}" aria-hidden="true">{{ $colour['initials'] }}</span>
