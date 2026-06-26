<x-guest-layout>
    <div class="ck-mb-4 ck-text-muted">
        Bitte bestätige deine E-Mail-Adresse. Wir haben dir einen Bestätigungslink geschickt.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="ck-flash ck-flash--success ck-mb-4">
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse gesendet.
        </div>
    @endif

    <div class="ck-form-actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-ck-button type="submit" variant="primary">Bestätigungslink erneut senden</x-ck-button>
        </form>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <x-ck-button type="submit" variant="secondary">Abmelden</x-ck-button>
        </form>
    </div>
</x-guest-layout>
