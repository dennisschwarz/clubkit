/**
 * teams-modal.js
 * Steuert Team-Modal (Anlegen / Bearbeiten) und Accordion (Kader inline).
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Daten kommen aus window.CK_Teams (Data Bridge in Blade)
 *
 * Emittierte Ereignisse:
 *   ck:team.modal.open  → { mode, teamId, team }
 */

(function () {
    'use strict';

    var data = window.CK_Teams || {};

    function el(id) { return document.getElementById(id); }

    // ── Team-Modal öffnen ─────────────────────────────────────────────────

    window.teamsModalOpen = function (mode, teamId) {
        teamId = teamId || null;
        var form        = el('teamForm');
        var methodInput = el('teamFormMethod');
        var routes      = data.routes || {};

        if (!form) return;

        // Formular zurücksetzen
        form.reset();
        _setChecked('tFieldActive', true);
        _setChecked('tFieldIsCompetition', false);
        _setChecked('tFieldEligibleOnly', false);
        teamsToggleCompetition(false);

        // Farbe zurück auf Standard setzen
        _setColor('');

        // Tab zurück auf "Team-Daten"
        var firstTab = el('teamDatenTabBtn');
        if (firstTab) ckModalTab('teamModal', 'teamTab-daten', firstTab);

        if (mode === 'create') {
            _setTitle('Team anlegen');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'create', teamId: null, team: null });

        } else if (mode === 'edit' && teamId) {
            var t = (data.teams || {})[teamId];
            if (!t) return;

            _setTitle('„' + t.name + '" bearbeiten');
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + teamId;

            _setField('tFieldName', t.name);
            _setColor(t.color || '');
            _setChecked('tFieldActive', !!t.is_active);
            _setChecked('tFieldIsCompetition', !!t.is_competition);
            _setChecked('tFieldEligibleOnly',  !!t.eligible_only);
            teamsToggleCompetition(!!t.is_competition);

            if (t.is_competition) {
                _setField('tFieldSeason',   t.season    || '');
                _setField('tFieldAgeClass', t.age_class || '');
                _setField('tFieldLeague',   t.league    || '');
            }

            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'edit', teamId: teamId, team: t });
        }
    };

    // ── Wettbewerbs-Block aufklappen/zuklappen ────────────────────────────

    window.teamsToggleCompetition = function (open) {
        var body    = el('tCompetitionBody');
        var chevron = el('tCompetitionChevron');
        if (!body) return;
        if (open) {
            body.classList.remove('is-hidden');
            if (chevron) chevron.classList.add('ck-competition-block__chevron--open');
        } else {
            body.classList.add('is-hidden');
            if (chevron) chevron.classList.remove('ck-competition-block__chevron--open');
        }
    };

    // ── Farb-Swatch-Picker initialisieren ─────────────────────────────────

    function _initColorPicker() {
        var picker = el('tColorPicker');
        if (!picker) return;

        var swatches = picker.querySelectorAll('.ck-color-swatch');

        for (var i = 0; i < swatches.length; i++) {
            (function (sw) {
                sw.addEventListener('click', function () {
                    var all = picker.querySelectorAll('.ck-color-swatch');
                    for (var j = 0; j < all.length; j++) {
                        all[j].classList.remove('ck-color-swatch--selected');
                    }
                    sw.classList.add('ck-color-swatch--selected');
                });
            })(swatches[i]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    function _setField(id, value) {
        var input = el(id);
        if (input) input.value = value;
    }

    function _setChecked(id, checked) {
        var input = el(id);
        if (input) input.checked = !!checked;
    }

    function _setTitle(text) {
        var t = document.querySelector('#teamModal .ck-modal__title');
        if (t) t.textContent = text;
    }

    // Setzt den aktiven Swatch auf den übergebenen Farbschlüssel ('' = Standard).
    function _setColor(colorKey) {
        var picker = el('tColorPicker');
        if (!picker) return;

        var swatches = picker.querySelectorAll('.ck-color-swatch');
        for (var i = 0; i < swatches.length; i++) {
            swatches[i].classList.remove('ck-color-swatch--selected');
            var input = swatches[i].querySelector('input[type="radio"]');
            if (input && input.value === colorKey) {
                swatches[i].classList.add('ck-color-swatch--selected');
                input.checked = true;
            }
        }
    }

    // ── Initialisierung ───────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        _initColorPicker();
    });

}());
