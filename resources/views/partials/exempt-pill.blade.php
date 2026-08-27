@if ($exemption)
    <span class="pill shrink-0 {{ $exemptPill }}" title="{{ $blocking ? __('app.absence.no_target') : __('app.absence.marker') }}">
        <x-icon :name="$exemption['absence']?->type->icon() ?? 'calendar-days'" class="size-3"/>
        {{ $exemption['label'] }}
    </span>
@endif
