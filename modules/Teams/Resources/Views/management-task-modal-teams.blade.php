{{--
    Teams-Hook: Team-Checkboxen im Aufgaben-Modal.
    Extension point: management.task.modal.teams
    Registriert von: TeamsServiceProvider

    Data: $ckTeams (Collection<Team>)
    — per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
@if($ckTeams->isNotEmpty())
<div class="ck-field ck-mt-4">
    <span class="ck-field__label">Teams <span class="ck-text-muted ck-font-normal">(optional)</span></span>
    <div class="ck-multiselect-list" id="mgmtTaskTeamList">
        @foreach($ckTeams as $ckTeam)
        <label class="ck-multiselect-item">
            <input type="checkbox" name="team_ids[]" value="{{ $ckTeam->id }}" class="ck-multiselect-item__checkbox">
            <span class="ck-multiselect-item__label">{{ $ckTeam->name }}</span>
        </label>
        @endforeach
    </div>
    <p class="ck-field__hint">Ohne Team-Auswahl erscheint die Aufgabe unter „Allgemein".</p>
</div>
@endif
