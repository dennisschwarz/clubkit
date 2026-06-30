{{--
    Teams-Hook: Team-Checkboxen im Funktions-Modal.
    Extension point: management.function.modal.teams
    Registriert von: TeamsServiceProvider

    Das DOM-Element mgmtFunctionTeamList wird von management-page-scripts.blade.php
    per Event-Listener (ck:management.function.modal.open) vorbefüllt.

    Data: $ckTeams (Collection<Team>)
    — per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
@if($ckTeams->isNotEmpty())
<div class="ck-field ck-mt-4">
    <span class="ck-field__label">Teams <span class="ck-text-muted ck-font-normal">(optional)</span></span>
    <div class="ck-multiselect-list" id="mgmtFunctionTeamList">
        @foreach($ckTeams as $ckTeam)
        <label class="ck-multiselect-item">
            <input type="checkbox" name="team_ids[]" value="{{ $ckTeam->id }}" class="ck-multiselect-item__checkbox">
            <span class="ck-multiselect-item__label">{{ $ckTeam->name }}</span>
        </label>
        @endforeach
    </div>
    <p class="ck-field__hint">Ohne Team-Auswahl erscheint die Funktion unter „Allgemein".</p>
</div>
@endif
