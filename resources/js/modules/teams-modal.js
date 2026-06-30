/**
 * teams-modal.js
 * Controls the Team modal (create / edit) and the competition-block toggle.
 *
 * Rules:
 *  - No el.style.*  → classList only
 *  - Data comes from window.CK_Teams (Data Bridge in Blade)
 *
 * Emitted events:
 *   ck:team.modal.open  → { mode, teamId, team }
 */

(function () {
    'use strict';

    const data = window.CK_Teams || {};

    function el(id) { return document.getElementById(id); }

    // ── Team-Modal öffnen ─────────────────────────────────────────────────

    window.teamsModalOpen = function (mode, teamId) {
        teamId = teamId || null;
        const form        = el('teamForm');
        const methodInput = el('teamFormMethod');
        const routes      = data.routes || {};

        if (!form) return;

        // Reset form to defaults
        form.reset();
        _setChecked('tFieldActive', true);
        _setChecked('tFieldIsCompetition', false);
        _setChecked('tFieldEligibleOnly', false);
        teamsToggleCompetition(false);

        // Reset color picker to default
        _setColor('');

        // Return to first tab
        const firstTab = el('teamDatenTabBtn');
        if (firstTab) ckModalTab('teamModal', 'teamTab-daten', firstTab);

        if (mode === 'create') {
            _setTitle('Team anlegen');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'create', teamId: null, team: null });

        } else if (mode === 'edit' && teamId) {
            const t = (data.teams || {})[teamId];
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

    // ── Competition-Block expand/collapse ─────────────────────────────────

    window.teamsToggleCompetition = function (open) {
        const body    = el('tCompetitionBody');
        const chevron = el('tCompetitionChevron');
        if (!body) return;
        if (open) {
            body.classList.remove('is-hidden');
            if (chevron) chevron.classList.add('ck-competition-block__chevron--open');
        } else {
            body.classList.add('is-hidden');
            if (chevron) chevron.classList.remove('ck-competition-block__chevron--open');
        }
    };

    // ── Color swatch picker ───────────────────────────────────────────────

    function _initColorPicker() {
        const picker = el('tColorPicker');
        if (!picker) return;

        const swatches = picker.querySelectorAll('.ck-color-swatch');

        for (let i = 0; i < swatches.length; i++) {
            (function (sw) {
                sw.addEventListener('click', function () {
                    const all = picker.querySelectorAll('.ck-color-swatch');
                    for (let j = 0; j < all.length; j++) {
                        all[j].classList.remove('ck-color-swatch--selected');
                    }
                    sw.classList.add('ck-color-swatch--selected');
                });
            })(swatches[i]);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    function _setField(id, value) {
        const input = el(id);
        if (input) input.value = value;
    }

    function _setChecked(id, checked) {
        const input = el(id);
        if (input) input.checked = !!checked;
    }

    function _setTitle(text) {
        const t = document.querySelector('#teamModal .ck-modal__title');
        if (t) t.textContent = text;
    }

    // Sets the active swatch to the given color key ('' = default/no color).
    function _setColor(colorKey) {
        const picker = el('tColorPicker');
        if (!picker) return;

        const swatches = picker.querySelectorAll('.ck-color-swatch');
        for (let i = 0; i < swatches.length; i++) {
            swatches[i].classList.remove('ck-color-swatch--selected');
            const input = swatches[i].querySelector('input[type="radio"]');
            if (input && input.value === colorKey) {
                swatches[i].classList.add('ck-color-swatch--selected');
                input.checked = true;
            }
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        _initColorPicker();
    });

}());
