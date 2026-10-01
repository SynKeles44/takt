<x-app-layout :title="__('app.packages.title')" :wide="true">
    <x-dev-page :title="__('app.packages.title')" :hint="__('app.packages.intro')" active="packages">
        <x-slot:actions>
            <form method="POST" action="{{ route('packages.check.all') }}" data-busy>
                @csrf
                <button type="submit" class="btn btn-ghost text-xs">
                    <x-icon name="repeat" class="size-3.5"/>
                    <span data-busy-label="{{ __('app.packages.checking') }}">{{ __('app.packages.check_all') }}</span>
                </button>
            </form>
        </x-slot:actions>

        <div class="mt-4 grid gap-2 border-t border-line pt-4 sm:grid-cols-3 xl:grid-cols-5" data-stagger>
            @foreach ([
                ['label' => __('app.packages.title'), 'value' => $summary['total'], 'tone' => 'neutral'],
                ['label' => __('app.packages.status.minor'), 'value' => $summary['minor'], 'tone' => 'accent'],
                ['label' => __('app.packages.status.major'), 'value' => $summary['major'], 'tone' => 'danger'],
                ['label' => __('app.packages.security'), 'value' => $summary['vulnerable'], 'tone' => 'danger'],
                ['label' => __('app.packages.never_checked'), 'value' => $summary['unchecked'], 'tone' => 'muted'],
            ] as $tile)
                <div class="tile px-3 py-2.5">
                    <p class="metric text-xl font-bold tracking-tight
                        @class([
                            'text-danger-text' => $tile['tone'] === 'danger' && $tile['value'] > 0,
                            'text-accent-text' => $tile['tone'] === 'accent' && $tile['value'] > 0,
                            'text-ink' => $tile['tone'] === 'neutral',
                            'text-dim' => $tile['value'] === 0 || $tile['tone'] === 'muted',
                        ])"
                       data-count="{{ $tile['value'] }}">{{ $tile['value'] }}</p>
                    <p class="mt-0.5 text-[10px] leading-tight text-faint">{{ $tile['label'] }}</p>
                </div>
            @endforeach
        </div>
    </x-dev-page>

    {{-- Security first: old is a chore, vulnerable is work. --}}
    <x-card class="mt-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="heading">{{ __('app.packages.security') }}</h2>
                <p class="mt-0.5 text-[11px] text-faint">
                    {{ __('app.packages.security_hint', ['composer' => 'composer audit', 'npm' => 'npm audit']) }}
                </p>
            </div>

            @if ($advisories !== [])
                <span class="pill border-danger/30 bg-danger/10 text-[10px] text-danger-text">{{ count($advisories) }}</span>
            @endif
        </div>

        @if ($advisories === [])
            <x-empty class="mt-3" pose="cheer" compact :hint="__('app.packages.security_none')"/>
        @else
            <ul class="mt-3 space-y-1.5" data-auto-animate>
                @foreach ($advisories as $advisory)
                    <li class="row flex flex-wrap items-start gap-x-3 gap-y-1 px-3 py-2.5"
                        style="--row-accent: {{ in_array($advisory['severity'], ['critical', 'high'], true) ? 'var(--color-danger)' : 'var(--color-rest)' }}"
                        @class(['row-accent'])>
                        <span @class([
                                'pill mt-0.5 shrink-0 text-[9px]',
                                'border-danger/40 bg-danger/15 text-danger-text' => in_array($advisory['severity'], ['critical', 'high'], true),
                                'border-rest/30 bg-rest/10 text-rest-text' => $advisory['severity'] === 'medium',
                                'text-faint' => $advisory['severity'] === 'low',
                            ])>{{ __('app.packages.severity.'.$advisory['severity']) }}</span>

                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="metric text-xs font-semibold text-ink">{{ $advisory['package'] }}</span>
                                <span class="pill text-[9px] text-faint">{{ $advisory['project']->name }}</span>
                                <span class="pill text-[9px] text-faint">{{ $advisory['manager'] }}</span>
                                @if ($advisory['cve'])
                                    <span class="metric text-[10px] text-dim">{{ $advisory['cve'] }}</span>
                                @endif
                            </p>

                            <p class="mt-0.5 text-xs leading-snug text-muted">{{ $advisory['title'] }}</p>

                            @if ($advisory['affected'] !== '')
                                <p class="metric mt-0.5 text-[10px] text-faint">{{ __('app.packages.affected', ['range' => $advisory['affected']]) }}</p>
                            @endif
                        </div>

                        <span class="flex shrink-0 items-center gap-1">
                            @if ($advisory['link'])
                                <a href="{{ $advisory['link'] }}" target="_blank" rel="noreferrer"
                                   class="icon-action size-7" title="{{ __('app.packages.advisory') }}">
                                    <x-icon name="external" class="size-3.5"/>
                                </a>
                            @endif

                            <form method="POST" action="{{ route('packages.update', $advisory['project']) }}" data-busy>
                                @csrf
                                <input type="hidden" name="manager" value="{{ $advisory['manager'] }}">
                                <input type="hidden" name="package" value="{{ $advisory['package'] }}">
                                <button type="submit" class="btn btn-ghost text-[10px]">
                                    <span data-busy-label="{{ __('app.packages.updating') }}">{{ __('app.packages.update') }}</span>
                                </button>
                            </form>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- The cross-project view no single project can produce. --}}
    <x-card class="mt-5">
        <details>
            <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                <x-icon name="chevron-right" class="size-3.5 text-dim"/>
                <span class="heading">{{ __('app.packages.shared') }}</span>
                @if ($shared !== [])
                    <span class="pill text-[10px]">{{ count($shared) }}</span>
                @endif
            </summary>

            <p class="mt-2 text-[11px] leading-snug text-faint">{{ __('app.packages.shared_hint') }}</p>

            @if ($shared === [])
                <p class="mt-3 text-xs text-faint">{{ __('app.packages.shared_none') }}</p>
            @else
                <ul class="mt-3 space-y-1.5">
                    @foreach ($shared as $entry)
                        <li class="row flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2">
                            <span class="metric min-w-0 flex-1 truncate text-xs text-ink">{{ $entry['name'] }}</span>
                            <span class="pill shrink-0 text-[9px] text-faint">{{ $entry['manager'] }}</span>

                            <span class="flex flex-wrap items-center gap-1.5">
                                @foreach ($entry['versions'] as $version => $projects)
                                    <span class="pill metric shrink-0 text-[9px] {{ $loop->first ? 'border-work/30 bg-work/10 text-work-text' : 'text-dim' }}"
                                          title="{{ implode(', ', $projects) }}">
                                        {{ $version }} · {{ count($projects) }}
                                    </span>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </details>
    </x-card>

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
        <h2 class="heading">{{ trans_choice('app.packages.projects_count', $summary['projects'], ['count' => $summary['projects']]) }}</h2>

        <div class="segmented" data-package-filter>
            <button type="button" class="segment segment-active" data-filter="all">{{ __('app.packages.all') }}</button>
            <button type="button" class="segment" data-filter="outdated">{{ __('app.packages.only_outdated') }}</button>
        </div>
    </div>

    @forelse ($rows as $row)
        @php $project = $row['project']; @endphp

        <x-card class="mt-3" data-package-project data-reveal>
            {{-- Collapsed by default: the summary line is the answer most visits need. --}}
            <details>
                <summary class="flex cursor-pointer flex-wrap items-center justify-between gap-3">
                    <span class="flex min-w-0 items-center gap-2">
                        <x-icon name="chevron-right" class="size-3.5 shrink-0 text-dim"/>
                        <span class="truncate text-sm font-semibold text-ink">{{ $project->name }}</span>
                    </span>

                    <span class="flex flex-wrap items-center gap-1.5">
                        @if (($row['counts']['vulnerable'] ?? 0) > 0)
                            <span class="pill border-danger/40 bg-danger/15 text-[10px] text-danger-text">
                                {{ $row['counts']['vulnerable'] }} {{ __('app.packages.security') }}
                            </span>
                        @endif

                        @if ($row['counts']['major'] > 0)
                            <span class="pill border-danger/30 bg-danger/10 text-[10px] text-danger-text">{{ $row['counts']['major'] }} {{ __('app.packages.status.major') }}</span>
                        @endif

                        @if ($row['counts']['minor'] > 0)
                            <span class="pill border-accent/30 bg-accent/10 text-[10px] text-accent-text">{{ $row['counts']['minor'] }} {{ __('app.packages.status.minor') }}</span>
                        @endif

                        @if ($row['checked_at'] !== null && $row['counts']['minor'] === 0 && $row['counts']['major'] === 0)
                            <span class="pill text-[10px] text-faint">{{ __('app.packages.nothing_outdated') }}</span>
                        @endif

                        <span class="metric text-[10px] text-faint">{{ $row['counts']['total'] }}</span>

                        @if ($row['checked_at'] === null)
                            <span class="pill text-[10px] text-dim">{{ __('app.packages.never_checked') }}</span>
                        @endif
                    </span>
                </summary>

                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3">
                    <p class="text-[11px] text-faint">
                        @if ($row['checked_at'] !== null)
                            {{ __('app.packages.checked_at', ['time' => $row['checked_at']->diffForHumans()]) }}
                        @else
                            {{ __('app.packages.never_checked') }}
                        @endif
                    </p>

                    <form method="POST" action="{{ route('packages.check', $project) }}" data-busy>
                        @csrf
                        <button type="submit" class="btn btn-ghost text-[11px]">
                            <x-icon name="repeat" class="size-3"/>
                            <span data-busy-label="{{ __('app.packages.checking') }}">{{ __('app.packages.check') }}</span>
                        </button>
                    </form>
                </div>

                @foreach ($row['managers'] as $manager => $packages)
                    <div class="mt-4">
                        <h3 class="heading">{{ $manager }}</h3>

                        <ul class="mt-2 space-y-1.5" data-auto-animate>
                            @foreach ($packages as $package)
                                <li class="row flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2"
                                    data-package-row data-status="{{ isset($package['vulnerable']) ? 'vulnerable' : $package['status'] }}">
                                    <span class="metric min-w-0 flex-1 truncate text-xs text-ink">{{ $package['name'] }}</span>

                                    @if ($package['dev'])
                                        <span class="pill shrink-0 text-[9px] text-faint">{{ __('app.packages.dev') }}</span>
                                    @endif

                                    <span class="metric shrink-0 text-[11px] text-dim">{{ $package['current'] ?: $package['constraint'] }}</span>

                                    @if ($package['latest'] !== null && $package['latest'] !== $package['current'])
                                        <span class="metric shrink-0 text-[11px] text-faint" aria-hidden="true">→</span>
                                        <span class="metric shrink-0 text-[11px] text-accent-text">{{ $package['latest'] }}</span>
                                    @endif

                                    @isset($package['vulnerable'])
                                        <span class="pill shrink-0 border-danger/40 bg-danger/15 text-[9px] text-danger-text">
                                            {{ __('app.packages.vulnerable') }}
                                        </span>
                                    @else
                                        <span @class([
                                                'pill shrink-0 text-[9px]',
                                                'border-danger/30 bg-danger/10 text-danger-text' => in_array($package['status'], ['major', 'abandoned'], true),
                                                'border-accent/30 bg-accent/10 text-accent-text' => $package['status'] === 'minor',
                                                'text-faint' => in_array($package['status'], ['current', 'unknown', 'missing'], true),
                                            ])>{{ __('app.packages.status.'.$package['status']) }}</span>
                                    @endisset

                                    @if (in_array($package['status'], ['minor', 'major'], true) || isset($package['vulnerable']))
                                        <form method="POST" action="{{ route('packages.update', $project) }}" data-busy>
                                            @csrf
                                            <input type="hidden" name="manager" value="{{ $manager }}">
                                            <input type="hidden" name="package" value="{{ $package['name'] }}">
                                            <button type="submit" class="btn btn-ghost text-[10px]">
                                                <span data-busy-label="{{ __('app.packages.updating') }}">{{ __('app.packages.update') }}</span>
                                            </button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </details>
        </x-card>
    @empty
        <x-card class="mt-3">
            <x-empty pose="search" :hint="__('app.packages.empty')"/>
        </x-card>
    @endforelse

    <p class="mt-4 text-[11px] leading-snug text-faint">{{ __('app.packages.hint') }}</p>
    <x-mascot pose="package" class="mascot-at-tail-right size-20"/>
</x-app-layout>
