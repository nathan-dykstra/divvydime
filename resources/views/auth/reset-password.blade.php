<x-guest-layout>
    <div class="container">
        <div class="guest-app-logo margin-bottom-lg">
            <a href="{{ route('welcome') }}">
                <h1>{{ config('app.name') }}</h1>
            </a>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="space-bottom-sm">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>

            <div class="btn-container-end">
                <x-primary-button type="submit">{{ __('Reset Password') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-guest-layout>
