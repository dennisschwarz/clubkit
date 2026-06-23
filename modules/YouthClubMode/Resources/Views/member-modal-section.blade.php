{{--
    YouthClubMode – Hook View: member.modal.sections
    Fügt die Guardians-Section in das Member-Modal ein.

    Verfügbare Variablen (automatisch übergeben via @ckHook):
      $members      – paginierte Mitglieder-Collection
      $membersJs    – JS data bridge (bereits mit father_id / mother_id angereichert)
      $parentOptions – Array [member_id => 'Nachname, Vorname'] für Dropdowns
                       (durch YouthClubMode View Composer injiziert)
--}}
<div id="memberTab-guardian" class="ck-modal__section">

    {{-- Hinweis im Create-Modus: wird via JS ein-/ausgeblendet --}}
    <div id="memberGuardianCreateHint" class="ck-flash ck-flash--warning is-hidden">
        Bitte zuerst das Mitglied speichern (Tab Stammdaten), dann Erziehungsberechtigte zuweisen.
    </div>

    <form id="memberGuardianForm" method="POST">
        @csrf
        {{-- PATCH /members/{id}/parents – action wird per JS gesetzt --}}
        <input type="hidden" name="_method" value="PATCH">

        <p class="ck-text-muted ck-mb-4">
            Vater und Mutter müssen als eigene Mitglieder-Einträge existieren.
            Leer lassen, um eine bestehende Verknüpfung zu entfernen.
        </p>

        <div class="ck-form-grid ck-form-grid--2">
            <x-ck-field label="Vater" name="father_id" type="select"
                id="mFieldFatherId" :options="$parentOptions ?? []" />
            <x-ck-field label="Mutter" name="mother_id" type="select"
                id="mFieldMotherId" :options="$parentOptions ?? []" />
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="submit" variant="primary">Erziehungsberechtigte speichern</x-ck-button>
            <x-ck-button type="button" variant="secondary"
                onclick="ckModalClose(null, 'memberModal')">Abbrechen</x-ck-button>
        </div>
    </form>

</div>
