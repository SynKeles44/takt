@props(['todo'])

@php $state = $todo->dueState(); @endphp

@if ($todo->due_at)
    <span {{ $attributes->class(['pill metric', $state->pillClasses()]) }}
          title="{{ $state->label() }} · {{ $todo->due_at->isoFormat('dddd, D. MMMM YYYY HH:mm') }}">
        <x-icon :name="$state->isUrgent() ? 'alert' : 'calendar'" class="size-3.5"/>
        {{ $todo->dueLabel() }}
    </span>
@endif
