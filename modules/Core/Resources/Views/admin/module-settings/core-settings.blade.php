{{--
    Hook-View: admin.module-settings.sections → Core-Einstellungen
    Wird vom CoreServiceProvider als erste Sektion registriert.
    $ckSettings ist global via View::share verfügbar.
--}}
<x-ck-card class="ck-mb-5">

    <x-slot:header>⚙️ Core</x-slot:header>

    <form method="POST" action="{{ route('admin.module-settings.core.update') }}">
        @csrf
        @method('PATCH')

        {{-- Registrierung erlauben --}}
        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Registrierung erlauben</div>
                <div class="ck-settings-row__hint">
                    Neue Benutzer können sich über die Willkommensseite selbst registrieren.
                    Ist diese Option deaktiviert, ist der Registrierungsbutton ausgeblendet
                    und der Endpunkt <code>/register</code> gesperrt.
                </div>
            </div>
            <div class="ck-settings-row__input">
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="registration_enabled"
                           value="1"
                           {{ ($ckSettings['registration_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
                    Aktivieren
                </label>
            </div>
        </div>

        <div class="ck-mt-4">
            <x-ck-button type="submit" variant="primary" size="sm">Speichern</x-ck-button>
        </div>

    </form>

</x-ck-card>