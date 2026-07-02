{{--
    YouthClubMode – Hook view: member.modal.sections
    Family tab inside the member modal.

    All logic (dropdown filtering, list rendering, AJAX) lives in youth-club-mode.js.
    This view provides only the HTML skeleton.
--}}
<div id="memberTab-family" class="ck-modal__section">

    {{-- Create-mode hint (shown/hidden by JS) --}}
    <div id="memberFamilyCreateHint" class="ck-flash ck-flash--warning is-hidden">
        Bitte zuerst das Mitglied speichern (Tab Stammdaten), dann Familienmitglieder zuweisen.
    </div>

    {{-- ── Add connection ──────────────────────────────────────── --}}
    <div class="ck-family-add">
        <p class="ck-section-label ck-mb-3">Verbindung hinzufügen</p>
        <div class="ck-row ck-family-add__row">

            {{-- Dropdown 1: relationship type --}}
            <select id="mFieldRelationship" class="ck-field__input">
                <option value="">– Beziehung wählen –</option>
                <option value="father">Vater (des Mitglieds)</option>
                <option value="mother">Mutter (des Mitglieds)</option>
                <option value="father_of">Vater von …</option>
                <option value="mother_of">Mutter von …</option>
                <option value="sibling">Geschwister</option>
            </select>

            {{-- Dropdown 2: member (starts disabled, populated by JS) --}}
            <select id="mFieldRelatedMember" class="ck-field__input" disabled>
                <option value="">– erst Beziehung wählen –</option>
            </select>

            <x-ck-button type="button" id="mBtnAddRelation" variant="primary" size="sm" disabled>
                {{ __('+ Add') }}
            </x-ck-button>

        </div>
        {{-- Error area for AJAX errors --}}
        <div id="mFamilyAddError" class="ck-flash ck-flash--error ck-mt-3 is-hidden"></div>
    </div>

    {{-- ── Current connections (rendered by JS) ──────────────── --}}
    <div class="ck-mt-4">
        <p class="ck-section-label ck-mb-3">Aktuelle Verbindungen</p>
        <div id="mFamilyList">
            <p class="ck-text-muted">Noch keine Verbindungen eingetragen.</p>
        </div>
    </div>

</div>
