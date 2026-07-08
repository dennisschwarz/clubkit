/**
 * teams-modal.js
 * Controls the Team modal (create / edit), competition-block toggle,
 * and the Roster Dual Listbox modal.
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

    // ── Open team edit modal ──────────────────────────────────────────────

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
        _setColor('');

        // Return to first tab
        const firstTab = el('teamDatenTabBtn');
        if (firstTab) ckModalTab('teamModal', 'teamTab-daten', firstTab);

        if (mode === 'create') {
            _setTitle(ckUi('team_create', 'Team anlegen'));
            methodInput.value = 'POST';
            form.action       = routes.store || '';

            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'create', teamId: null, team: null });

        } else if (mode === 'edit' && teamId) {
            const t = (data.teams || {})[teamId];
            if (!t) return;

            _setTitle('„' + t.name + '"' + ckUi('edit_suffix', ' bearbeiten'));
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

    // ── Roster Dual Listbox modal ─────────────────────────────────────────

    /**
     * Opens the Roster modal for a given team.
     * Populates the available (left) and current roster (right) select lists,
     * and sets the form action to the correct syncRoster URL.
     *
     * @param {number} teamId
     */
    window.openRosterModal = function (teamId) {
        const t         = (data.teams     || {})[teamId];
        const roster    = (data.roster    || {})[teamId] || [];
        const available = (data.available || {})[teamId] || [];
        const form      = el('rosterForm');
        const titleEl   = document.querySelector('#teamRosterModal .ck-modal__title');

        if (!t) return;

        if (titleEl) titleEl.textContent = ckUi('roster_prefix', 'Kader: ') + t.name;
        if (form)    form.action = (data.routes.syncRoster || '') + '/' + teamId + '/members/sync';

        _populateSelect('rosterAvail',    available);
        _populateSelect('rosterCurrent',  roster);

        ckModalOpen('teamRosterModal');
    };

    /**
     * Moves selected options between the Available ↔ Roster selects.
     *
     * @param {'right'|'left'} direction
     */
    window.ckRosterMove = function (direction) {
        const fromId = direction === 'right' ? 'rosterAvail' : 'rosterCurrent';
        const toId   = direction === 'right' ? 'rosterCurrent' : 'rosterAvail';
        const from   = el(fromId);
        const to     = el(toId);
        if (!from || !to) return;

        // Collect and move selected options
        const selected = Array.from(from.options).filter(function (o) { return o.selected; });
        selected.forEach(function (opt) {
            opt.selected = false;
            to.appendChild(opt);
        });
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

    // ── Roster form: select all right-side options before submit ──────────

    function _initRosterForm() {
        const form = el('rosterForm');
        if (!form) return;

        form.addEventListener('submit', function () {
            const right = el('rosterCurrent');
            if (!right) return;
            // Select all options so they are all submitted as member_ids[].
            // An empty right-side list will result in no member_ids[] being sent,
            // which syncRoster() interprets as "clear entire roster".
            Array.from(right.options).forEach(function (o) { o.selected = true; });
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────

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

    /**
     * Replaces all options in a <select> with the given member array.
     *
     * @param {string}                             selectId
     * @param {Array<{id: number, name: string}>}  members
     */
    function _populateSelect(selectId, members) {
        const sel = el(selectId);
        if (!sel) return;

        sel.innerHTML = '';
        members.forEach(function (m) {
            const opt       = document.createElement('option');
            opt.value       = m.id;
            opt.textContent = m.name;
            sel.appendChild(opt);
        });
    }

    // ── Init ──────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        _initColorPicker();
        _initRosterForm();
    });

}());
