@extends('core::admin.layout')

@section('title', 'Erscheinungsbild')

@section('content')

<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Erscheinungsbild</h1>
        <p class="ck-page-subtitle">Änderungen in Farb-Sektionen werden sofort angewendet. Logo und Vereinsname erfordern einen Seitenreload.</p>
    </div>
</div>

{{-- ════════════════════════════════════════════════
     VEREINSIDENTITÄT
════════════════════════════════════════════════ --}}
<x-ck-card>

    <x-slot:header>Vereinsidentität</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            Speichern
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        {{-- Vereinsname --}}
        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Vereinsname</div>
                <div class="ck-settings-row__hint">Angezeigt im Header und im Browser-Tab.</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="text"
                       name="club_name"
                       class="ck-field__input"
                       data-setting
                       value="{{ $settings['club_name'] }}"
                       maxlength="60"
                       placeholder="z. B. HSV Langenfeld 1959 e.V.">
            </div>
        </div>

        {{-- Logo --}}
        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Logo</div>
                <div class="ck-settings-row__hint">JPEG, PNG oder WEBP – max. 3 MB.</div>
            </div>
            <div class="ck-settings-row__input">
                @if(!empty($settings['logo_path']))
                    <img src="{{ asset('storage/' . $settings['logo_path']) }}"
                         alt="Aktuelles Logo"
                         class="ck-appearance__logo-preview">
                @endif
                <input type="file"
                       name="logo"
                       class="ck-field__input"
                       data-setting
                       accept="image/jpeg,image/png,image/webp">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     BRAND-BAR (Zeile 1 im Header)
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>Brand-Bar (Logo-Zeile)</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            Speichern
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hintergrundfarbe</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="header_bg" class="ck-field__input" data-setting
                       value="{{ $settings['header_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Schriftfarbe</div>
                <div class="ck-settings-row__hint">Vereinsname, Username</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="brand_bar_text" class="ck-field__input" data-setting
                       value="{{ $settings['brand_bar_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hoverfarbe</div>
                <div class="ck-settings-row__hint">Username und Abmelden beim Hover</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="brand_bar_hover" class="ck-field__input" data-setting
                       value="{{ $settings['brand_bar_hover'] }}">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     NAVIGATIONSLEISTE (Zeile 2 im Header)
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>Navigationsleiste</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            Speichern
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Emojis anzeigen</div>
                <div class="ck-settings-row__hint">z. B. 🏠 Dashboard, 👥 Mitglieder</div>
            </div>
            <div class="ck-settings-row__input">
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="nav_bar_show_emojis"
                           class="ck-field__input"
                           data-setting
                           value="1"
                           {{ $settings['nav_bar_show_emojis'] === '1' ? 'checked' : '' }}>
                    Emojis anzeigen
                </label>
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hintergrundfarbe</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_bg" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Schriftfarbe</div>
                <div class="ck-settings-row__hint">Inaktive Tabs</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_text" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hoverfarbe</div>
                <div class="ck-settings-row__hint">Aktiver Tab + Hover</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_hover" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_hover'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Aktiver Highlightbalken</div>
                <div class="ck-settings-row__hint">2px-Linie unter dem aktiven Tab</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="nav_bar_active_bar" class="ck-field__input" data-setting
                       value="{{ $settings['nav_bar_active_bar'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Schriftgröße</div>
            </div>
            <div class="ck-settings-row__input">
                <select name="nav_bar_font_size" class="ck-field__input" data-setting>
                    @foreach($fontSizes as $size)
                        <option value="{{ $size }}"
                            {{ $settings['nav_bar_font_size'] === $size ? 'selected' : '' }}>
                            {{ $size }} px
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     SUB-TAB-LEISTE
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>Sub-Tab-Leiste</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            Speichern
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Emojis anzeigen</div>
                <div class="ck-settings-row__hint">z. B. 🖥️ System, 👤 Nutzer</div>
            </div>
            <div class="ck-settings-row__input">
                <label class="ck-field__checkbox">
                    <input type="checkbox"
                           name="subtab_show_emojis"
                           class="ck-field__input"
                           data-setting
                           value="1"
                           {{ $settings['subtab_show_emojis'] === '1' ? 'checked' : '' }}>
                    Emojis anzeigen
                </label>
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hintergrundfarbe</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_bg" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_bg'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Schriftfarbe</div>
                <div class="ck-settings-row__hint">Inaktive Sub-Tabs</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_text" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_text'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Hoverfarbe</div>
                <div class="ck-settings-row__hint">Aktiver Sub-Tab + Hover</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_hover" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_hover'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Aktiver Highlightbalken</div>
                <div class="ck-settings-row__hint">2px-Linie unter dem aktiven Sub-Tab</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="subtab_active_bar" class="ck-field__input" data-setting
                       value="{{ $settings['subtab_active_bar'] }}">
            </div>
        </div>

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Schriftgröße</div>
            </div>
            <div class="ck-settings-row__input">
                <select name="subtab_font_size" class="ck-field__input" data-setting>
                    @foreach($fontSizes as $size)
                        <option value="{{ $size }}"
                            {{ $settings['subtab_font_size'] === $size ? 'selected' : '' }}>
                            {{ $size }} px
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

    </div>

</x-ck-card>

{{-- ════════════════════════════════════════════════
     ALLGEMEIN
════════════════════════════════════════════════ --}}
<x-ck-card class="ck-mt-5">

    <x-slot:header>Allgemein</x-slot:header>

    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
        <x-ck-button type="button" variant="primary" size="sm" data-appearance-save>
            Speichern
        </x-ck-button>
    </x-slot:headerAction>

    <div class="ck-settings-section">

        <div class="ck-settings-row">
            <div>
                <div class="ck-settings-row__label">Seitenhintergrund</div>
            </div>
            <div class="ck-settings-row__input">
                <input type="color" name="body_bg" class="ck-field__input" data-setting
                       value="{{ $settings['body_bg'] }}">
            </div>
        </div>

    </div>

</x-ck-card>

{{-- Logo löschen (nur wenn Logo vorhanden) --}}
@if(!empty($settings['logo_path']))
<x-ck-card class="ck-mt-5">
    <x-slot:header>Logo entfernen</x-slot:header>
    <x-slot:headerAction>
        <span data-save-status class="ck-save-status"></span>
    </x-slot:headerAction>
    <form method="POST" action="{{ route('admin.appearance.logo.delete') }}">
        @csrf
        @method('DELETE')
        <x-ck-button
            variant="danger"
            type="submit"
            :confirm="'Logo wirklich entfernen? Die Datei wird dauerhaft gelöscht.'">
            Logo entfernen
        </x-ck-button>
    </form>
</x-ck-card>
@endif

@push('scripts')
{{-- Data Bridge: Route + aktuelle CSS-Variablen für das JS --}}
<script>
window.CK_Appearance = {
    routes: {
        update: "{{ route('admin.appearance.update') }}"
    }
};
</script>
@vite('resources/js/modules/appearance-modal.js')
@endpush

@endsection
