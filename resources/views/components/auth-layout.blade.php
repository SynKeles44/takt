@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="midnight" data-style="soft">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-dvh place-items-center px-4 py-10 font-sans text-ink antialiased">
    <div class="w-full max-w-sm">
        <div class="flex flex-col items-center gap-3 text-center">
            <x-logo class="size-14"/>
            <div>
                <p class="text-2xl font-bold tracking-tight brand-gradient">{{ config('app.name') }}</p>
                <p class="text-xs text-faint">{{ __('app.tagline') }}</p>
            </div>
        </div>

        <div class="surface rise mt-8">
            <h1 class="text-base font-semibold text-ink">{{ $title }}</h1>

            @if ($errors->any())
                <div class="mt-4 rounded-[var(--radius-control)] border border-danger/30 bg-danger/10 px-3.5 py-2.5 text-xs text-danger-text">
                    <ul class="space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="mt-4 rounded-[var(--radius-control)] border border-work/30 bg-work/10 px-3.5 py-2.5 text-xs text-work-text">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mt-5">
                {{ $slot }}
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-dim">{{ $footer ?? '' }}</p>
    </div>
</body>
</html>
