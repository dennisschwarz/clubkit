<x-guest-layout>
    <div class="ck-mb-4 ck-text-muted">
        Passwort vergessen? Gib deine E-Mail-Adresse ein und wir senden dir einen Link zum Zurücksetzen.
    </div>

    <x-auth-session-status class="ck-mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-ck-field label="E-Mail-Adresse" name="email" type="email"
            id="email" :value="old('email')" :required="true" autofocus />
        @error('email')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button type="submit" variant="primary">Link zum Zurücksetzen senden</x-ck-button>
        </div>
    </form>
</x-guest-layout>
