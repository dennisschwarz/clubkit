{{--
    YouthClubMode – Hook View: member.modal.sections
    Familie-Tab im Member-Modal.

    Die gesamte Logik (Dropdown-Filterung, Liste, AJAX) liegt in youth-club-mode.js.
    Diese View liefert nur das HTML-Gerüst.
--}}
<div id="memberTab-family" class="ck-modal__section">

    {{-- Hinweis im Create-Modus (JS zeigt/versteckt) --}}
    <div id="memberFamilyCreateHint" class="ck-flash ck-flash--warning is-hidden">
        Bitte zuerst das Mitglied speichern (Tab Stammdaten), dann Familienmitglieder zuweisen.
    </div>

    {{-- ── Verbindung hinzufügen ──────────────────────────────────────── --}}
    <div class="ck-family-add">
        <p class="ck-section-label ck-mb-3">Verbindung hinzufügen</p>
        <div class="ck-row ck-family-add__row">

            {{-- Dropdown 1: Beziehungsart --}}
            <select id="mFieldRelationship" class="ck-field__input">
                <option value="">– Beziehung wählen –</option>
                <option value="father">Vater (des Mitglieds)</option>
                <option value="mother">Mutter (des Mitglieds)</option>
                <option value="father_of">Vater von …</option>
                <option value="mother_of">Mutter von …</option>
                <option value="sibling">Geschwister</option>
            </select>

            {{-- Dropdown 2: Mitglied (startet deaktiviert, wird per JS gefüllt) --}}
            <select id="mFieldRelatedMember" class="ck-field__input" disabled>
                <option value="">– erst Beziehung wählen –</option>
            </select>

            <x-ck-button type="button" id="mBtnAddRelation" variant="primary" size="sm" disabled>
                + Hinzufügen
            </x-ck-button>

        </div>
        {{-- Fehler-Bereich für AJAX-Fehler --}}
        <div id="mFamilyAddError" class="ck-flash ck-flash--error ck-mt-3 is-hidden"></div>
    </div>

    {{-- ── Aktuelle Verbindungen (wird von JS gerendert) ──────────────── --}}
    <div class="ck-mt-4">
        <p class="ck-section-label ck-mb-3">Aktuelle Verbindungen</p>
        <div id="mFamilyList">
            <p class="ck-text-muted">Noch keine Verbindungen eingetragen.</p>
        </div>
    </div>

</div>
