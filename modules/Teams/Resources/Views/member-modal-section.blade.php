<div id="memberTab-teams" class="ck-modal__section">

    @if($ckAllTeams->isEmpty())

        <p class="ck-text-muted">Noch keine Teams vorhanden.</p>

    @else

        <p class="ck-text-muted ck-font-sm ck-mb-3">
            Mitglied zu Teams hinzufügen oder entfernen. Speichern schreibt alle Änderungen.
        </p>

        {{--
            Checkboxes styled via .ck-multiselect-list (forms.css).
            JS (member-teams.js) checks/unchecks on modal open and handles AJAX save.
            Save flow: modal closes immediately → loading overlay → fetch → ckNotify().
            The .ck-save-status span is intentionally removed: feedback is now via toast.
        --}}
        <div class="ck-multiselect-list ck-multiselect-list--scrollable">
            @foreach($ckAllTeams as $ckTeam)
            <label class="ck-multiselect-item">
                <input type="checkbox"
                       class="ck-multiselect-item__checkbox ck-member-team-check"
                       value="{{ $ckTeam->id }}">
                <span class="ck-multiselect-item__label">
                    <x-ck-badge :color="'team-' . ($ckTeam->color ?: 'default')">
                        {{ $ckTeam->name }}
                    </x-ck-badge>
                    @if(!$ckTeam->is_active)
                        <span class="ck-text-muted ck-font-xs">(inaktiv)</span>
                    @endif
                </span>
            </label>
            @endforeach
        </div>

        <div class="ck-form-actions">
            <x-ck-button type="button" variant="primary" onclick="memberTeamsSave()">
                Speichern
            </x-ck-button>
            <x-ck-button type="button" variant="secondary" onclick="ckModalClose(null, 'memberModal')">
                Abbrechen
            </x-ck-button>
        </div>

    @endif

</div>
