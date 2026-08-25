@php
    $field = 'control';
    $label = 'label';
@endphp

<x-auth-layout :title="__('app.auth.login_title')">
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="{{ $label }}">{{ __('app.auth.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="{{ $field }}"
                   required autofocus autocomplete="username" inputmode="email">
        </div>

        <div>
            <label for="password" class="{{ $label }}">{{ __('app.auth.password') }}</label>
            <input id="password" type="password" name="password" class="{{ $field }}"
                   required autocomplete="current-password">
        </div>

        <label class="flex items-center gap-2.5 text-sm text-muted">
            <input type="checkbox" name="remember" value="1"
                   class="size-4 accent-[var(--color-accent)]">
            {{ __('app.auth.remember') }}
        </label>

        <button type="submit"
                class="btn btn-primary w-full">
            <x-icon name="logout" class="size-4 rotate-180"/>
            {{ __('app.auth.login_action') }}
        </button>
    </form>

    <x-slot:footer>
        {{ __('app.auth.no_account') }}
        <a href="{{ route('register') }}" class="font-semibold text-accent-text hover:underline">{{ __('app.auth.register_action') }}</a>
    </x-slot:footer>
</x-auth-layout>
