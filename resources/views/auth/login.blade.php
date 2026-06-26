<x-guest-layout>
    <x-auth-session-status class="ck-mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <x-ck-field label="E-Mail-Adresse" name="email" type="email"
            id="email" :value="old('email')" :required="true"
            autocomplete="username" autofocus />
        @error('email')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field label="Passwort" name="password" type="password"
            id="password" :required="true" autocomplete="current-password"
            class="ck-mt-4" />
        @error('password')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <div class="ck-mt-4">
            <x-ck-field type="checkbox" name="remember" id="remember_me">
                Angemeldet bleiben
            </x-ck-field>
        </div>

        <div class="ck-form-actions ck-mt-4">
            @if (Route::has('password.request'))
                <a class="ck-link" href="{{ route('password.request') }}">
                    Passwort vergessen?
                </a>
            @endif
            <x-ck-button type="submit" variant="primary">Anmelden</x-ck-button>
        </div>
    </form>
</x-guest-layout>
