<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-ck-field label="E-Mail-Adresse" name="email" type="email"
            id="email" :value="old('email', $request->email)" :required="true"
            autocomplete="username" />
        @error('email')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field label="Neues Passwort" name="password" type="password"
            id="password" :required="true" autocomplete="new-password"
            class="ck-mt-4" />
        @error('password')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field label="Passwort bestätigen" name="password_confirmation"
            type="password" id="password_confirmation" :required="true"
            autocomplete="new-password" class="ck-mt-4" />
        @error('password_confirmation')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button type="submit" variant="primary">Passwort zurücksetzen</x-ck-button>
        </div>
    </form>
</x-guest-layout>
