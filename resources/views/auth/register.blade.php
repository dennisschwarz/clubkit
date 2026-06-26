<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <x-ck-field label="Name" name="name" id="name"
            :value="old('name')" :required="true" autofocus
            autocomplete="name" />
        @error('name')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field label="E-Mail-Adresse" name="email" type="email"
            id="email" :value="old('email')" :required="true"
            autocomplete="username" class="ck-mt-4" />
        @error('email')
            <p class="ck-form-error">{{ $message }}</p>
        @enderror

        <x-ck-field label="Passwort" name="password" type="password"
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
            <a class="ck-link" href="{{ route('login') }}">
                Bereits registriert?
            </a>
            <x-ck-button type="submit" variant="primary">Registrieren</x-ck-button>
        </div>
    </form>
</x-guest-layout>
