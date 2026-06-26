@extends('core::admin.layout')
@section('title', 'Spalten zuordnen')

@section('content')
<div class="ck-page-header">
    <div>
        <h1 class="ck-page-title">Mitglieder importieren</h1>
        <p class="ck-page-subtitle">Schritt 2 von 3 – Spalten zuordnen</p>
    </div>
    <div class="ck-row ck-row--gap">
        @if($customFieldsEnabled)
        <x-ck-button variant="secondary" size="sm" onclick="ckModalOpen('cfDefModal')">
            + Custom Field anlegen
        </x-ck-button>
        @endif
        <form method="POST" action="{{ route('import.cancel', $session->id) }}">
            @csrf
            <x-ck-button variant="secondary" type="submit">Abbrechen</x-ck-button>
        </form>
    </div>
</div>

{{-- Info-Banner: DFBnet-Geschlechtskennzeichen --}}
@if($session->source === 'dfbnet')
<div class="ck-alert ck-alert--info ck-mb-4">
    ℹ️ <strong>DFBnet-Format erkannt:</strong>
    Die Spalte „Vorname Rufname" enthält Geschlechtskennzeichen <code>(w)</code> / <code>(m)</code>
    für den Rufnamen — diese werden automatisch als Geschlecht übernommen und aus dem Vornamen entfernt.
    Beispiel: <code>Maryam (w)</code> → Vorname: <em>Maryam</em>, Geschlecht: <em>Weiblich</em>.
</div>
@endif

<x-ck-card>
    <x-slot:header>
        <span>
            <strong>{{ $session->filename }}</strong>
            &nbsp;·&nbsp;
            {{ count($session->raw_rows) }} Datensätze erkannt
            &nbsp;·&nbsp;
            Quelle: <x-ck-badge color="blue">{{ strtoupper($session->source) }}</x-ck-badge>
        </span>
    </x-slot:header>

    <form method="POST" action="{{ route('import.mapping.save', $session->id) }}">
        @csrf

        <p class="ck-mb-4">
            Weise jeder erkannten CSV-Spalte ein Mitgliederfeld zu oder wähle
            <em>„Überspringen"</em>, wenn das Feld nicht importiert werden soll.
        </p>

        <div class="ck-table-wrap">
            <table class="ck-table">
                <thead>
                    <tr>
                        <th class="ck-table__col--md">CSV-Spalte</th>
                        <th>Beispielwerte</th>
                        <th class="ck-table__col--lg">Zuordnen zu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($session->column_headers as $header)
                    <tr>
                        <td><strong>{{ $header }}</strong></td>
                        <td>
                            @foreach ($session->samples[$header] ?? [] as $sample)
                                <x-ck-badge color="gray">{{ Str::limit($sample, 30) }}</x-ck-badge>
                            @endforeach
                        </td>
                        <td>
                            {{-- .ck-mapping-select wird vom Import-JS genutzt um neue CF-Optionen einzufügen --}}
                            <select name="mapping[{{ $header }}]" class="ck-field__input ck-mapping-select">

                                {{-- Überspringen --}}
                                <option value="skip"
                                    {{ ($suggested[$header] ?? 'skip') === 'skip' ? 'selected' : '' }}>
                                    — Überspringen —
                                </option>

                                {{-- Mitgliederfelder --}}
                                <optgroup label="Mitglied-Felder">
                                    @foreach ($memberFields as $fieldKey => $fieldLabel)
                                    <option value="{{ $fieldKey }}"
                                        {{ ($suggested[$header] ?? '') === $fieldKey ? 'selected' : '' }}>
                                        {{ $fieldLabel }}
                                    </option>
                                    @endforeach
                                </optgroup>

                                {{-- Custom Fields --}}
                                @if ($customFieldsEnabled)
                                    @if(count($customFields))
                                    <optgroup label="Custom Fields">
                                        @foreach ($customFields as $cf)
                                        <option value="cf:{{ $cf['slug'] }}">
                                            {{ $cf['label'] }} ({{ $cf['field_type'] }})
                                        </option>
                                        @endforeach
                                    </optgroup>
                                    @endif
                                @endif

                            </select>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="ck-form-actions ck-mt-4">
            <x-ck-button variant="primary" type="submit">
                Zuordnung bestätigen und Vorschau laden →
            </x-ck-button>
        </div>
    </form>
</x-ck-card>

{{-- ══ Custom Field anlegen (nur wenn CF-Modul installiert) ══════════════════ --}}
@if($customFieldsEnabled)
{{--
    Das cfDefModal wird hier inline gerendert (nicht über module-settings-section.blade.php).
    object_type ist als hidden input vorausgefüllt (Mitglied), kein Dropdown.
    Die Form-Submission wird via AJAX abgefangen (siehe @push('scripts') unten):
    → POST /custom-fields mit Accept: application/json
    → JSON-Response: {id, slug, label, field_type}
    → Neue Option wird dynamisch in alle .ck-mapping-select Dropdowns eingefügt.
--}}
<x-ck-modal id="cfDefModal" title="Custom Field anlegen" size="md">
    <div class="ck-modal__section ck-modal__section--active">
        <form id="cfDefForm" method="POST" action="{{ route('custom-fields.store') }}">
            @csrf
            <input type="hidden" name="_method" id="cfDefMethod" value="POST">

            {{-- object_type ist fest auf 'member' gesetzt – kein Dropdown --}}
            <input type="hidden" name="object_type" value="member">
            <p class="ck-text-muted ck-mb-4">
                <small>Feld wird für den Objekt-Typ <strong>Mitglied</strong> angelegt.</small>
            </p>

            <x-ck-field label="Feldname" name="label" id="cfDefLabel"
                        placeholder="z.B. Trikotgröße, Verein vorher" :required="true" />

            <div class="ck-form-grid ck-form-grid--2 ck-mt-3">
                <x-ck-field type="select" label="Feldtyp" name="field_type"
                            id="cfDefFieldType" :required="true"
                            :options="array_merge(['' => '– auswählen –'], $fieldTypes)" />
                <x-ck-field label="Platzhaltertext" name="placeholder"
                            id="cfDefPlaceholder" placeholder="z.B. M, L, XL" />
            </div>

            {{-- Optionen: nur bei field_type='select' sichtbar --}}
            <div class="ck-mt-3 is-hidden" id="cfDefOptionsBlock">
                <x-ck-field type="textarea" label="Auswahloptionen (eine pro Zeile)"
                            name="options_raw" id="cfDefOptionsRaw" rows="4"
                            placeholder="Option A&#10;Option B&#10;Option C" />
            </div>

            <div class="ck-mt-3">
                <x-ck-field type="checkbox" name="is_required" id="cfDefIsRequired" value="1">
                    Pflichtfeld
                </x-ck-field>
            </div>

            <div class="ck-form-actions">
                <x-ck-button type="submit" variant="primary" id="cfDefSubmitBtn">Anlegen</x-ck-button>
                <x-ck-button type="button" variant="secondary"
                    onclick="ckModalClose(null, 'cfDefModal')">Abbrechen</x-ck-button>
            </div>
        </form>
    </div>
</x-ck-modal>
@endif

@endsection

@push('scripts')
{{-- custom-fields-modal.js für _toggleOptionsBlock beim Feldtyp-Wechsel --}}
@if($customFieldsEnabled)
<script src="{{ asset('js/modules/custom-fields-modal.js') }}"></script>
<script>
/**
 * Import-Kontext: CF-Feld via AJAX anlegen und Dropdowns dynamisch aktualisieren.
 *
 * Die cfDefForm-Submission wird hier intercepted (statt normalem POST-Redirect).
 * Bei Erfolg: neue CF-Option in alle .ck-mapping-select Dropdowns einfügen.
 *
 * Kein el.style.* – nur classList-Operationen.
 */
(function () {
    'use strict';

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content
        || (document.querySelector('[name="_token"]') || {}).value
        || '';

    document.addEventListener('DOMContentLoaded', function () {
        var cfDefForm    = document.getElementById('cfDefForm');
        var cfSubmitBtn  = document.getElementById('cfDefSubmitBtn');

        if (!cfDefForm) return;

        cfDefForm.addEventListener('submit', function (e) {
            e.preventDefault();

            if (cfSubmitBtn) cfSubmitBtn.disabled = true;

            var formData = new FormData(cfDefForm);
            // object_type ist bereits als hidden input mit value='member' im Formular

            fetch(cfDefForm.action, {
                method:  'POST',
                headers: {
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     csrfToken,
                },
                body: formData,
            })
            .then(function (resp) {
                if (!resp.ok) {
                    return resp.json().then(function (d) {
                        var msg = d.message || Object.values(d.errors || {}).flat().join(', ') || 'Fehler';
                        throw new Error(msg);
                    });
                }
                return resp.json();
            })
            .then(function (data) {
                // Neue Option in alle Mapping-Dropdowns einfügen
                var optVal  = 'cf:' + data.slug;
                var optText = data.label + ' (' + data.field_type + ')';

                document.querySelectorAll('.ck-mapping-select').forEach(function (select) {
                    // Nicht doppelt einfügen
                    for (var i = 0; i < select.options.length; i++) {
                        if (select.options[i].value === optVal) return;
                    }

                    // optgroup "Custom Fields" suchen oder neu erstellen
                    var optgroup = select.querySelector('optgroup[label="Custom Fields"]');
                    if (!optgroup) {
                        optgroup = document.createElement('optgroup');
                        optgroup.label = 'Custom Fields';
                        select.appendChild(optgroup);
                    }

                    var opt = document.createElement('option');
                    opt.value       = optVal;
                    opt.textContent = optText;
                    optgroup.appendChild(opt);
                });

                // Modal schließen + Formular zurücksetzen
                ckModalClose(null, 'cfDefModal');
                cfDefForm.reset();
                var optBlock = document.getElementById('cfDefOptionsBlock');
                if (optBlock) optBlock.classList.add('is-hidden');
            })
            .catch(function (err) {
                alert('Fehler beim Anlegen: ' + err.message);
            })
            .finally(function () {
                if (cfSubmitBtn) cfSubmitBtn.disabled = false;
            });
        });
    });
}());
</script>
@endif
@endpush
