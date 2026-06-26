<x-guest-layout>
    <div class="ck-mb-4 ck-text-muted">
        Bitte bestätige dein Passwort, bevor du fortfährst.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <x-ck-field label="Passwort" name="password" type="password"
            id="password" :required="true" autocomplete="current-password" />
        @error('password')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button type="submit" variant="primary">Bestätigen</x-ck-button>
        </div>
    </form>
</x-guest-layout>
