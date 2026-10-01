<x-card>
    <div class="flex items-center justify-between gap-3">
        <h2 class="heading">{{ __('app.widget.packages.label') }}</h2>
        <a href="{{ route('packages') }}" class="pill hover:text-ink">{{ __('app.dev.manage') }}</a>
    </div>

    <div class="mt-4 grid grid-cols-4 gap-2 text-center">
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold text-ink">{{ $summary['total'] }}</p>
            <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.packages.total') }}</p>
        </div>
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold {{ $summary['major'] > 0 ? 'text-danger-text' : 'text-dim' }}">{{ $summary['major'] }}</p>
            <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.packages.major') }}</p>
        </div>
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold {{ $summary['minor'] > 0 ? 'text-ink' : 'text-dim' }}">{{ $summary['minor'] }}</p>
            <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.packages.minor') }}</p>
        </div>
        <div class="row px-2 py-2.5">
            <p class="metric text-xl font-bold {{ $summary['vulnerable'] > 0 ? 'text-danger-text' : 'text-work-text' }}">{{ $summary['vulnerable'] }}</p>
            <p class="mt-0.5 truncate text-[10px] uppercase tracking-wide text-faint">{{ __('app.widget.packages.vulnerable') }}</p>
        </div>
    </div>

    <div class="mt-4 space-y-1.5">
        @forelse ($advisories as $advisory)
            <a href="{{ route('packages') }}" class="row flex items-center gap-3 px-3 py-2">
                <x-icon name="alert" class="size-3.5 shrink-0 text-danger-text"/>
                <span class="min-w-0 flex-1">
                    <span class="metric block truncate text-sm text-ink">{{ $advisory['package'] }}</span>
                    <span class="block truncate text-[11px] text-dim">{{ $advisory['project']->name }} · {{ $advisory['title'] }}</span>
                </span>
                <span class="pill shrink-0 text-[10px]">{{ $advisory['severity'] }}</span>
            </a>
        @empty
            <p class="rounded-[var(--radius-control)] border border-dashed border-line px-3 py-5 text-center text-xs text-faint">
                {{ $summary['unchecked'] > 0
                    ? __('app.widget.packages.unchecked', ['count' => $summary['unchecked']])
                    : __('app.widget.packages.clean') }}
            </p>
        @endforelse
    </div>
</x-card>
