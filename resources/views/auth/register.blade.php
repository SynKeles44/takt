@php
    $field = 'control';
    $label = 'label';
@endphp

<x-auth-layout :title="__('app.auth.register_title')">
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="{{ $label }}">{{ __('app.auth.name') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" class="{{ $field }}"
                   required autofocus autocomplete="name" maxlength="120">
        </div>

        <div>
            <label for="email" class="{{ $label }}">{{ __('app.auth.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" class="{{ $field }}"
                   required autocomplete="username" inputmode="email">
        </div>

        <div>
            <label for="password" class="{{ $label }}">{{ __('app.auth.password') }}</label>
            <input id="password" type="password" name="password" class="{{ $field }}"
                   required autocomplete="new-password">
        </div>

        <div>
            <label for="password_confirmation" class="{{ $label }}">{{ __('app.auth.password_confirm') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="{{ $field }}"
                   required autocomplete="new-password">
        </div>

        <button type="submit"
                class="btn btn-primary w-full">
            <x-icon name="check" class="size-4"/>
            {{ __('app.auth.register_action') }}
        </button>
    </form>

    <x-slot:footer>
        {{ __('app.auth.has_account') }}
        <a href="{{ route('login') }}" class="font-semibold text-accent-text hover:underline">{{ __('app.auth.login_action') }}</a>
    </x-slot:footer>
</x-auth-layout>
