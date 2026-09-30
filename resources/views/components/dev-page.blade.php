@props(['title', 'hint' => null, 'active'])

{{--
    The head every development page wears. It exists because "the header looks different here"
    was raised twice: the first time each page was aligned by hand, which fixed the pages that
    existed and did nothing for the next one. A shared component is the version that holds —
    a new page cannot drift without deleting this line.
--}}
<x-card class="rise">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>

            @if ($hint)
                <p class="mt-0.5 text-xs text-faint">{{ $hint }}</p>
            @endif

            {{ $lead ?? '' }}
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{ $actions ?? '' }}
            <x-dev-tabs :active="$active"/>
        </div>
    </div>

    {{ $slot }}
</x-card>
