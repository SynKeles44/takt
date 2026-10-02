@props(['title' => null, 'wide' => false, 'defer' => false])

@php
    $user = auth()->user();
    $theme = $user?->theme ?? \App\Enums\Theme::Midnight;
    $style = $user?->design_style ?? \App\Enums\DesignStyle::Soft;

    $sections = \App\Support\Sidebar::forUser($user);
@endphp

<!DOCTYPE html>
@php $native = str_contains((string) request()->userAgent(), 'TaktShell'); @endphp
{{--
    `data-defer` is the page saying "what you see is the shape, the numbers are still coming".
    A view sets $defer when it rendered skeletons instead of the slow read.
--}}
{{--
    Inside the app window the page arrives whole: no view transition and no entrance animation.
    Photographed from inside the window, the system WebKit captured named elements as blank
    images and painted the new page with an empty content column for a third of a second — on a
    dark scheme a flash of bare canvas on every click. `data-settled` is the mark the deferred
    loader uses after a swap; set from the start it switches every arrival animation off.
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $theme->resolved()->value }}" data-style="{{ $style->value }}"
      @if ($defer) data-defer="{{ request()->fullUrl() }}" @endif
      @if ($native) data-shell="native" data-settled @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($theme->isAutomatic())
        <script>
            (() => {
                const light = window.matchMedia('(prefers-color-scheme: light)');
                const apply = () => { document.documentElement.dataset.theme = light.matches ? 'daylight' : 'midnight'; };
                apply();
                light.addEventListener('change', apply);
            })();
        </script>
    @endif
    <script>
        (() => {
            if (localStorage.getItem('takt.nav') === 'collapsed') {
                document.documentElement.dataset.nav = 'collapsed';
            }
        })();
    </script>
    {{--
        A page carried in by a view transition has already arrived: the engine fades the old
        content out and this one in. Letting the cards rise a second time on top of that left the
        content area bare for a few hundred milliseconds on every click — on a light scheme that
        is white cards turning canvas-grey and back. `pagereveal` fires before the first frame,
        so the mark lands before any entrance animation could start; a cold load, which has no
        transition, keeps its arrival.
    --}}
    <script>
        addEventListener('pagereveal', (event) => {
            if (event.viewTransition) {
                document.documentElement.dataset.settled = '';
            }
        });
    </script>
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="{{ $theme->resolved()->preview()['canvas'] }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if ($native)
        {{-- after the stylesheet, so this is the rule that counts: the app window does not cross-fade pages --}}
        <style>@view-transition { navigation: none; }</style>
    @endif
</head>
<body class="min-h-dvh text-ink antialiased">
    @if (session('status'))
        <div class="pointer-events-none fixed inset-x-4 bottom-4 z-50 flex justify-center sm:inset-x-auto sm:bottom-6 sm:right-6 sm:justify-end">
            <div class="toast pointer-events-auto" data-autohide="{{ session('undo') ? 9000 : 4200 }}" role="status" aria-live="polite">
                <x-icon name="check" class="size-4 shrink-0"/>
                <span class="min-w-0 flex-1" data-flash>{{ session('status') }}</span>

                @if (session('undo'))
                    <form method="POST" action="{{ session('undo')['url'] }}" class="shrink-0" data-live>
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="pill border-work/40 bg-work/15 px-2 py-0.5 text-work-text hover:brightness-110">
                            <x-icon name="repeat" class="size-3"/>
                            {{ session('undo')['label'] }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="nav-shell lg:grid lg:min-h-dvh">
        <aside data-region="nav" class="nav-aside surface-plain sticky top-0 z-20 flex items-center gap-3 rounded-none border-x-0 border-t-0 py-3 backdrop-blur lg:h-dvh lg:flex-col lg:items-stretch lg:gap-5 lg:border-b-0 lg:py-6">
            <div class="nav-brand flex min-w-0 items-center gap-2.5">
                <span class="nav-logo-slot relative size-9 shrink-0">
                    <a href="{{ route('dashboard') }}" class="nav-logo block size-9">
                        <x-logo class="size-9"/>
                    </a>

                    <button type="button" data-nav-toggle
                            class="nav-expand absolute inset-0 place-items-center rounded-[var(--radius-control)] border border-line bg-raised text-muted transition hover:text-ink"
                            aria-label="{{ __('app.nav.expand') }}" title="{{ __('app.nav.expand') }}">
                        <x-icon name="chevron-right" class="size-[1.15rem]"/>
                    </button>
                </span>

                <span class="nav-label hidden min-w-0 leading-tight sm:block">
                    <span class="block text-base font-bold tracking-tight brand-gradient">{{ config('app.name') }}</span>
                    <span class="hidden truncate text-[11px] text-faint lg:block">{{ __('app.tagline_short') }}</span>
                </span>

                <button type="button" data-nav-toggle
                        class="icon-action nav-collapse ml-auto shrink-0"
                        aria-label="{{ __('app.nav.collapse') }}" title="{{ __('app.nav.collapse') }}">
                    <x-icon name="panel" class="size-[1.15rem]"/>
                </button>
            </div>

            <nav class="nav-list ml-auto flex items-center gap-0.5 sm:gap-1 lg:ml-0 lg:mt-1 lg:flex-col lg:items-stretch">
                @foreach ($sections as $section)
                    @php $current = $section['current']; @endphp

                    <a href="{{ $section['url'] }}"
                       @class(['nav-item', 'nav-item-active' => $current, 'nav-item-sub' => $section['depth'] === 1])
                       @if ($current) aria-current="page" @endif
                       title="{{ $section['label'] }}">
                        {{--
                            The highlight is its own element, not a background on the link, because
                            only an element can carry a view-transition-name — and that name is what
                            makes it travel to the next section instead of blinking off here and on
                            over there. It exists once per page, on whichever item is current.
                        --}}
                        @if ($current)
                            <span class="nav-marker" aria-hidden="true"></span>
                        @endif

                        <x-icon :name="$section['icon']" class="relative size-[1.15rem] shrink-0"/>
                        <span class="nav-label relative hidden sm:inline">{{ $section['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            <button type="button" data-palette-open title="{{ __('app.palette.open') }}"
                    class="nav-search hidden items-center gap-2 rounded-[var(--radius-control)] border border-line bg-raised text-xs font-semibold text-muted transition hover:bg-hover hover:text-ink lg:mt-auto lg:flex">
                <x-icon name="search" class="size-4 shrink-0"/>
                <span class="nav-label flex-1 text-left">{{ __('app.palette.open') }}</span>
                <span class="nav-label pill px-1.5 py-0 text-[10px]">⌘K</span>
            </button>

            <div class="nav-account relative flex items-center gap-2 lg:border-t lg:border-line lg:pt-4">
                <button type="button" data-account-toggle aria-haspopup="true" aria-expanded="false"
                        class="nav-account-trigger flex min-w-0 flex-1 items-center gap-2 rounded-[var(--radius-control)] text-left transition hover:bg-hover"
                        title="{{ $user?->name }}">
                    <span class="nav-avatar avatar hidden size-9 shrink-0 text-xs lg:grid">{{ $user?->initials() }}</span>

                    <span class="nav-label hidden min-w-0 flex-1 lg:block">
                        <span class="block truncate text-sm font-semibold text-ink">{{ $user?->name }}</span>
                        <span class="block truncate text-[11px] text-faint">{{ $user?->email }}</span>
                    </span>
                </button>

            </div>

        </aside>

        <div @class([
            'nav-main mx-auto w-full px-4 pb-16 pt-6 sm:px-6 lg:px-8 lg:pt-10',
            'max-w-5xl xl:max-w-6xl' => ! $wide,
            // the development page carries two dense columns; on 5xl the right one is a sliver
            'max-w-6xl xl:max-w-[92rem]' => $wide === true,
            /*
             * `full` is the window minus the gutters, and only the board asks for it. A board of
             * nine columns has nothing to do with a reading width — it was being cut off at 92rem
             * with empty desktop either side of it — while a page of prose at that width is worse
             * to read, not better.
             */
            'max-w-none' => $wide === 'full',
        ])>
            @if (app(\App\Support\BuildFreshness::class)->stale())
                {{-- the built assets are older than their sources: whatever is on screen is not what the code says --}}
                <div class="mb-5 flex items-center gap-3 rounded-[var(--radius-control)] border border-rest/40 bg-rest/10 px-4 py-3 text-sm text-rest-text" role="alert" data-stale-build>
                    <x-icon name="alert" class="size-4 shrink-0"/>
                    <span class="min-w-0 flex-1">{{ __('app.build.stale') }}</span>
                    {{-- answered as JSON: a success reloads the page onto the fresh assets, a failure shows its reason as a toast --}}
                    <form method="POST" action="{{ route('build') }}" class="shrink-0" data-async data-busy>
                        @csrf
                        <button type="submit" class="btn btn-ghost px-3 py-1.5 text-xs">
                            <x-icon name="repeat" class="size-3.5"/>
                            <span data-busy-label="{{ __('app.build.building') }}">{{ __('app.build.rebuild') }}</span>
                        </button>
                    </form>
                </div>
            @endif

            @if ($title)
                {{--
                    On a full-bleed page the heading keeps the normal page width, so it does not
                    walk out to the window edge while every other page's heading stays put. The
                    page itself opts the rest in with .page-width.
                --}}
                <h1 @class(['mb-5 text-xl font-bold tracking-tight text-ink', 'page-width' => $wide === 'full'])>{{ $title }}</h1>
            @endif

            @if ($errors->any())
                <div class="rise mb-5 rounded-[var(--radius-control)] border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger-text">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li class="flex gap-2"><span class="text-danger">•</span>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <main data-region="main">{{ $slot }}</main>
        </div>
    </div>

    <div data-account-menu
         class="nav-menu fixed z-[65] hidden w-60 p-1.5">
        <p class="px-2.5 pb-1.5 pt-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-faint">
            {{ __('app.nav.account') }}
        </p>

        {{-- the two pages of this menu are peers, so the highlight travels between them like any row --}}
        <div data-marker-row data-marker-key="account">
            @foreach ([
                ['settings', 'gear', __('app.nav.settings')],
                ['trash', 'trash', __('app.trash.title')],
            ] as [$route, $icon, $menuLabel])
                @php $here = request()->routeIs($route); @endphp

                <a href="{{ route($route) }}" @class(['nav-menu-item relative', 'nav-menu-item-active' => $here])>
                    @if ($here)
                        <span class="tab-marker" aria-hidden="true"></span>
                    @endif

                    <x-icon :name="$icon" class="relative size-4 shrink-0"/>
                    <span class="relative">{{ $menuLabel }}</span>
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-1">
            @csrf
            <button type="submit" class="nav-menu-item w-full">
                <x-icon name="logout" class="size-4 shrink-0"/>
                {{ __('app.nav.logout') }}
            </button>
        </form>
    </div>

    <x-palette/>
    <x-confirm-dialog/>

    {{--
        The wait. A ticket page reads Linear, GitHub and the local repositories before it can
        render, and on a cold cache that is a second or two in which a click looks like it did
        nothing. The marker is rendered here rather than built in JS so it is Takti himself and
        not a second drawing of him — it shows only once a navigation has already taken longer
        than a navigation normally does.
    --}}
    <div data-pending class="pending" hidden>
        <x-mascot pose="run" class="size-16"/>
        <span class="pending-label">{{ __('app.nav.loading') }}</span>
    </div>

    @if ($native)
        {{--
            The update notice of the downloaded app. The shell finds the newer release and calls
            window.takt.updateAvailable(); everything shown here is rendered by the server, so the
            words follow the user's language. Outside every region: no live swap removes it.
        --}}
        <div data-update class="update-notice surface-plain" role="status" aria-live="polite" hidden>
            <div class="flex items-start gap-3">
                <span class="update-notice-icon grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)]">
                    <x-icon name="download" class="size-4"/>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-ink" data-update-title data-template="{{ __('app.update.available', ['version' => ':version']) }}"></p>
                    <p class="mt-0.5 text-xs text-muted" data-update-text
                       data-idle="{{ __('app.update.idle', ['current' => ':current']) }}"
                       data-download="{{ __('app.update.download') }}"
                       data-verify="{{ __('app.update.verify') }}"
                       data-install="{{ __('app.update.install') }}"
                       data-restart="{{ __('app.update.restart') }}"
                       data-failed="{{ __('app.update.failed', ['reason' => ':reason']) }}"></p>

                    <div class="mt-3 flex flex-wrap items-center gap-2" data-update-actions>
                        <button type="button" class="btn btn-primary px-3 py-1.5 text-xs" data-update-install>
                            {{ __('app.update.action') }}
                        </button>
                        <button type="button" class="btn btn-ghost px-3 py-1.5 text-xs" data-update-notes>
                            {{ __('app.update.notes') }}
                        </button>
                        <button type="button" class="btn btn-ghost px-3 py-1.5 text-xs" data-update-later>
                            {{ __('app.update.later') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (! empty($dueWatch ?? []))
        <script type="application/json" data-due-watch>@json($dueWatch)</script>
    @endif

    @isset($shellState)
        {{-- the app shell reads this for its menu bar item, whatever the notification setting --}}
        <script type="application/json" data-shell-state
                data-away-url="{{ route('away.store') }}"
                data-calendar-url="{{ route('calendar.events') }}"
                data-trail-url="{{ route('trail.store') }}">@json($shellState)</script>
    @endisset

    @if (! empty($workWatch ?? []))
        <script type="application/json" data-work-watch
                data-label-target="{{ __('app.notify.target_title') }}"
                data-body-target="{{ __('app.notify.target_body') }}"
                data-label-break="{{ __('app.notify.break_title') }}"
                data-body-break="{{ __('app.notify.break_body') }}"
                data-label-max="{{ __('app.notify.max_title') }}"
                data-body-max="{{ __('app.notify.max_body') }}">@json($workWatch)</script>
    @endif
</body>
</html>
