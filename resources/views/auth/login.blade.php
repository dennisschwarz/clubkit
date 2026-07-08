<x-guest-layout>
    <x-auth-session-status class="ck-mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-ck-field :label="__('auth.email')" name="email" type="email"
            id="email" :value="old('email')" :required="true"
            autocomplete="username" autofocus />
        @error('email')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field :label="__('auth.password')" name="password" type="password"
            id="password" :required="true" autocomplete="current-password"
            class="ck-mt-4" />
        @error('password')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <div class="ck-mt-4">
            <x-ck-field type="checkbox" name="remember" id="remember_me">
                {{ __('auth.remember') }}
            </x-ck-field>
        </div>

        <div class="ck-form-actions ck-mt-4">
            @if (Route::has('password.request'))
                <a class="ck-link" href="{{ route('password.request') }}">
                    {{ __('auth.forgot_password') }}
                </a>
            @endif
            <x-ck-button type="submit" variant="primary">{{ __('auth.login') }}</x-ck-button>
        </div>
    </form>
</x-guest-layout>
