{{--
    Hook view: admin.module-settings.sections — Core settings (priority 5, active by default).
    $ckSettings is globally shared by CoreServiceProvider via View::share.
--}}
<div id="settings-core" class="ck-local-section ck-local-section--active">

    <div class="ck-section-header ck-section-header--colored ck-section-header--team-blue">
        <div class="ck-section-header__icon">⚙️</div>
        <div class="ck-section-header__text">
            <span class="ck-section-header__title">Core – Allgemeine Einstellungen</span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.module-settings.core.update') }}">
        @csrf
        @method('PATCH')

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
            <x-ck-button type="submit" variant="primary" size="sm">{{ __('Save') }}</x-ck-button>
        </div>

    </form>

</div>
