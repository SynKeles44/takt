<x-app-layout :title="__('app.dev.releases')" :wide="true" :defer="$defer">
    <x-dev-page :title="__('app.dev.releases')" :hint="__('app.dev.releases_intro')" active="releases"/>

    @if ($defer)
        <x-defer-skeleton :cards="3" :rows="3"/>
    @else
    <div class="stack mt-5">
        @if ($count === 0)
            <x-card class="rise">
                <p class="text-sm text-dim">{{ __('app.dev.releases_none') }}</p>
            </x-card>
        @endif

        @foreach ($groups as $group)
            <x-card @class(['rise' => $loop->first])>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="heading">{{ $group['project']->name }}</h2>
                    <span @class(['pill shrink-0 text-[10px]', 'text-dim' => $group['releases'] === []])>
                        {{ count($group['releases']) }}
                    </span>
                </div>

                @if ($group['error'])
                    <p class="mt-4 rounded-[var(--radius-control)] border border-rest/30 bg-rest/10 px-3 py-2 text-xs text-rest-text">
                        {{ $group['error'] }}
                    </p>
                @elseif ($group['releases'] === [])
                    <p class="mt-4 rounded-[var(--radius-control)] border border-dashed border-line px-3 py-4 text-center text-xs text-faint">
                        {{ __('app.dev.releases_empty') }}
                    </p>
                @else
                    <ul class="mt-4 space-y-1.5">
                        @foreach ($group['releases'] as $release)
                            <li class="row flex flex-wrap items-baseline gap-x-3 gap-y-1 px-3 py-2">
                                <span class="metric shrink-0 text-sm font-semibold text-accent-text">{{ $release['tag'] }}</span>
                                <span class="min-w-0 flex-1 text-sm text-ink">{{ $release['subject'] }}</span>
                                <span class="metric shrink-0 text-[11px] text-dim"
                                      title="{{ $release['at']->isoFormat('dd, D. MMMM YYYY, HH:mm') }}">
                                    {{ $release['at']->isoFormat('D. MMM YYYY') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        @endforeach
    </div>
    @endif
    <x-mascot pose="rocket" class="mascot-at-tail size-20"/>
</x-app-layout>
