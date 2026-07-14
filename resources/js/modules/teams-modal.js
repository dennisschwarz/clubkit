/**
 * teams-modal.js
 * Controls the Team modal (create / edit), competition-block toggle,
 * and the Roster assignment modal (SortableJS drag & drop).
 *
 * The Roster modal follows the exact same pattern as assign-modal.js
 * (taskAssignModal): two-panel list, real-time AJAX per add/remove,
 * Done button closes + refreshes the team's <tbody> via sort-fragment.
 *
 * Rules:
 *   - No el.style.*  → classList only
 *   - Data comes from window.CK_Teams (Data Bridge in Blade)
 */

(function () {
    'use strict';

    var data    = window.CK_Teams || {};
    var Sortable = window.Sortable || null;

    function el(id) { return document.getElementById(id); }

    // ── Current open roster team ───────────────────────────────────────────────

    var _currentTeamId    = null;
    var _sortableAvail    = null;
    var _sortableAssigned = null;

    // ── DOM item builders (same structure as assign-modal.js) ─────────────────

    function _availItem(memberId, name) {
        var li = document.createElement('li');
        li.className        = 'ck-assign-item';
        li.dataset.memberId = String(memberId);
        var span            = document.createElement('span');
        span.className      = 'ck-assign-item__name';
        span.textContent    = name;
        li.appendChild(span);
        return li;
    }

    function _assignedItem(memberId, name) {
        var li = document.createElement('li');
        li.className        = 'ck-assign-item';
        li.dataset.memberId = String(memberId);

        var nameSpan       = document.createElement('span');
        nameSpan.className = 'ck-assign-item__name';
        nameSpan.textContent = name;

        var removeBtn      = document.createElement('button');
        removeBtn.type     = 'button';
        removeBtn.className = 'ck-assign-item__remove';
        removeBtn.setAttribute('aria-label', 'Entfernen');
        removeBtn.textContent = '\u00d7';
        removeBtn.addEventListener('click', function () {
            _removeMember(li, memberId, name);
        });

        li.appendChild(nameSpan);
        li.appendChild(removeBtn);
        return li;
    }

    function _emptyState(listId) {
        var li       = document.createElement('li');
        li.className = 'ck-assign-list__empty';
        li.textContent = listId === 'teamRosterAssignList'
            ? 'Noch kein Mitglied im Kader.'
            : 'Alle Mitglieder bereits im Kader.';
        return li;
    }

    function _refreshEmptyStates() {
        ['teamRosterAvailList', 'teamRosterAssignList'].forEach(function (id) {
            var list     = el(id);
            if (! list) { return; }
            var existing = list.querySelector('.ck-assign-list__empty');
            var hasItems = list.querySelectorAll('.ck-assign-item').length > 0;
            if (hasItems && existing) { existing.remove(); }
            if (! hasItems && ! existing) { list.appendChild(_emptyState(id)); }
        });
    }

    // ── AJAX: add member to team ──────────────────────────────────────────────

    function _addMember(memberId, name, placeholder) {
        var routes = (data.routes || {});

        fetch(routes.addMemberBase + '/' + _currentTeamId + '/members', {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     (data.csrf || ''),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            body: JSON.stringify({ member_id: parseInt(memberId, 10) }),
        })
        .then(function (res) { return res.json(); })
        .then(function (resp) {
            placeholder.remove();
            if (resp.success) {
                var assignList = el('teamRosterAssignList');
                if (assignList) { assignList.appendChild(_assignedItem(memberId, name)); }
                _refreshEmptyStates();
            } else if (resp.error === 'already_assigned') {
                ckNotify('warning', 'Mitglied bereits im Kader.');
                var availList = el('teamRosterAvailList');
                if (availList) { availList.appendChild(_availItem(memberId, name)); }
                _refreshEmptyStates();
            } else {
                ckNotify('error', resp.message || 'Fehler beim Hinzufügen.');
                var availList2 = el('teamRosterAvailList');
                if (availList2) { availList2.appendChild(_availItem(memberId, name)); }
                _refreshEmptyStates();
            }
        })
        .catch(function () {
            if (placeholder.parentNode) { placeholder.remove(); }
            ckNotify('error', 'Netzwerkfehler.');
            var availList3 = el('teamRosterAvailList');
            if (availList3) { availList3.appendChild(_availItem(memberId, name)); }
            _refreshEmptyStates();
        });
    }

    // ── AJAX: remove member from team ─────────────────────────────────────────

    function _removeMember(li, memberId, name) {
        li.classList.add('ck-assign-item--pending');
        var routes = (data.routes || {});

        fetch(routes.addMemberBase + '/' + _currentTeamId + '/members/' + memberId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     (data.csrf || ''),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (resp) {
            if (resp.success) {
                var availList = el('teamRosterAvailList');
                if (availList) { availList.appendChild(_availItem(memberId, name)); }
                li.remove();
                _refreshEmptyStates();
            } else {
                li.classList.remove('ck-assign-item--pending');
                ckNotify('error', resp.message || 'Fehler beim Entfernen.');
            }
        })
        .catch(function () {
            li.classList.remove('ck-assign-item--pending');
            ckNotify('error', 'Netzwerkfehler.');
        });
    }

    // ── Open Roster modal ─────────────────────────────────────────────────────

    window.openRosterModal = function (teamId) {
        var t         = (data.teams     || {})[teamId];
        var roster    = (data.roster    || {})[teamId] || [];
        var available = (data.available || {})[teamId] || [];
        var titleEl   = document.querySelector('#teamRosterModal .ck-modal__title');

        if (! t) { return; }

        _currentTeamId = teamId;

        if (titleEl) { titleEl.textContent = 'Kader: ' + t.name; }

        var availList  = el('teamRosterAvailList');
        var assignList = el('teamRosterAssignList');
        if (! availList || ! assignList) { return; }

        availList.innerHTML  = '';
        assignList.innerHTML = '';

        // Populate assigned (current roster).
        roster.forEach(function (m) {
            assignList.appendChild(_assignedItem(m.id, m.name));
        });

        // Populate available.
        available.forEach(function (m) {
            availList.appendChild(_availItem(m.id, m.name));
        });

        _refreshEmptyStates();

        // Destroy existing SortableJS instances before re-creating.
        if (_sortableAvail)    { _sortableAvail.destroy();    _sortableAvail    = null; }
        if (_sortableAssigned) { _sortableAssigned.destroy(); _sortableAssigned = null; }

        if (Sortable) {
            // Available: clone source, not sortable.
            _sortableAvail = Sortable.create(availList, {
                group:     { name: 'team-roster', pull: 'clone', put: false },
                sort:      false,
                animation: 120,
            });

            // Assigned: sortable + accepts drops from available.
            _sortableAssigned = Sortable.create(assignList, {
                group:     { name: 'team-roster', pull: false, put: true },
                sort:      true,
                animation: 150,

                onAdd: function (evt) {
                    var clone    = evt.item;
                    var memberId = clone.dataset.memberId;
                    var name     = clone.querySelector('.ck-assign-item__name')
                        ? clone.querySelector('.ck-assign-item__name').textContent
                        : memberId;

                    clone.remove();

                    // Remove from available.
                    var src = availList.querySelector('[data-member-id="' + memberId + '"]');
                    if (src) { src.remove(); }

                    // Pending placeholder in assigned list.
                    var placeholder = _availItem(memberId, name);
                    placeholder.classList.add('ck-assign-item--pending');
                    assignList.appendChild(placeholder);
                    _refreshEmptyStates();

                    _addMember(memberId, name, placeholder);
                },
            });
        }

        ckModalOpen('teamRosterModal');
    };

    // ── Done button: close + refresh team tbody ────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var doneBtn = el('teamRosterDoneBtn');
        if (doneBtn) {
            doneBtn.addEventListener('click', function () {
                ckModalClose(null, 'teamRosterModal');
                _refreshTeamTbody(_currentTeamId);
            });
        }
    });

    function _refreshTeamTbody(teamId) {
        if (! teamId) { return; }
        var thead  = document.querySelector('thead[data-team-id="' + teamId + '"]');
        if (! thead) { return; }

        var sortCol = thead.dataset.sortCol || 'last_name';
        var sortDir = thead.dataset.sortDir || 'asc';
        var sort    = (sortDir === 'desc' ? '-' : '') + sortCol;
        var base    = (data.routes && data.routes.sortFragmentBase)
            ? data.routes.sortFragmentBase : '/teams';

        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        fetch(base + '/' + teamId + '/members/sort-fragment?sort=' + encodeURIComponent(sort), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            var tbody = thead.nextElementSibling;
            if (tbody && tbody.tagName === 'TBODY') {
                tbody.innerHTML = html;

                // Update member count in section header.
                var rows    = Array.from(tbody.querySelectorAll('tr'));
                var isEmpty = tbody.querySelector('td[colspan]');
                var count   = isEmpty ? 0 : rows.length;
                var countEl = document.querySelector('[data-team-member-count="' + teamId + '"]');
                if (countEl) { countEl.textContent = count; }
            }
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
        });
    }

    // ── Team create/edit modal ────────────────────────────────────────────────

    window.teamsModalOpen = function (mode, teamId) {
        teamId = teamId || null;
        var form        = el('teamForm');
        var methodInput = el('teamFormMethod');
        var routes      = data.routes || {};

        if (! form) { return; }

        form.reset();
        _setChecked('tFieldActive', true);
        _setChecked('tFieldIsCompetition', false);
        _setChecked('tFieldEligibleOnly', false);
        teamsToggleCompetition(false);
        _setColor('');

        var firstTab = el('teamDatenTabBtn');
        if (firstTab) { ckModalTab('teamModal', 'teamTab-daten', firstTab); }

        if (mode === 'create') {
            _setTitle(ckUi('team_create', 'Team anlegen'));
            methodInput.value = 'POST';
            form.action       = routes.store || '';
            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'create', teamId: null, team: null });

        } else if (mode === 'edit' && teamId) {
            var t = (data.teams || {})[teamId];
            if (! t) { return; }

            _setTitle('\u201e' + t.name + '\u201c' + ckUi('edit_suffix', ' bearbeiten'));
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + teamId;

            _setField('tFieldName', t.name);
            _setColor(t.color || '');
            _setChecked('tFieldActive',        !! t.is_active);
            _setChecked('tFieldIsCompetition', !! t.is_competition);
            _setChecked('tFieldEligibleOnly',  !! t.eligible_only);
            teamsToggleCompetition(!! t.is_competition);

            if (t.is_competition) {
                _setField('tFieldSeason',   t.season    || '');
                _setField('tFieldAgeClass', t.age_class || '');
                _setField('tFieldLeague',   t.league    || '');
            }

            ckModalOpen('teamModal');
            ckEmit('team.modal.open', { mode: 'edit', teamId: teamId, team: t });
        }
    };

    // ── Competition block toggle ──────────────────────────────────────────────

    window.teamsToggleCompetition = function (open) {
        var body    = el('tCompetitionBody');
        var chevron = el('tCompetitionChevron');
        if (! body) { return; }
        if (open) {
            body.classList.remove('is-hidden');
            if (chevron) { chevron.classList.add('ck-competition-block__chevron--open'); }
        } else {
            body.classList.add('is-hidden');
            if (chevron) { chevron.classList.remove('ck-competition-block__chevron--open'); }
        }
    };

    // ── AJAX column sort for team member tables ───────────────────────────────

    window.ckTeamSort = function (teamId, column, btn) {
        var thead   = btn.closest('thead');
        var prevCol = thead.dataset.sortCol || 'last_name';
        var prevDir = thead.dataset.sortDir || 'asc';
        var newDir  = (prevCol === column && prevDir === 'asc') ? 'desc' : 'asc';

        thead.dataset.sortCol = column;
        thead.dataset.sortDir = newDir;

        thead.querySelectorAll('.ck-sort-link').forEach(function (b) {
            b.classList.remove('ck-sort-link--active');
            b.querySelector('.ck-sort-icon').textContent = '\u21c5';
        });
        btn.classList.add('ck-sort-link--active');
        btn.querySelector('.ck-sort-icon').textContent = newDir === 'asc' ? '\u2191' : '\u2193';

        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        var sort = (newDir === 'desc' ? '-' : '') + column;
        var base = (data.routes && data.routes.sortFragmentBase)
            ? data.routes.sortFragmentBase : '/teams';

        fetch(base + '/' + teamId + '/members/sort-fragment?sort=' + encodeURIComponent(sort), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        })
        .then(function (res) {
            if (! res.ok) { throw new Error('HTTP ' + res.status); }
            return res.text();
        })
        .then(function (html) {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            var tbody = thead.nextElementSibling;
            if (tbody && tbody.tagName === 'TBODY') { tbody.innerHTML = html; }
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
        });
    };

    // ── Color swatch picker ───────────────────────────────────────────────────

    function _initColorPicker() {
        var picker = el('tColorPicker');
        if (! picker) { return; }
        picker.querySelectorAll('.ck-color-swatch').forEach(function (sw) {
            sw.addEventListener('click', function () {
                picker.querySelectorAll('.ck-color-swatch').forEach(function (s) {
                    s.classList.remove('ck-color-swatch--selected');
                });
                sw.classList.add('ck-color-swatch--selected');
            });
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    function _setField(id, value) {
        var input = el(id);
        if (input) { input.value = value; }
    }

    function _setChecked(id, checked) {
        var input = el(id);
        if (input) { input.checked = !! checked; }
    }

    function _setTitle(text) {
        var t = document.querySelector('#teamModal .ck-modal__title');
        if (t) { t.textContent = text; }
    }

    function _setColor(colorKey) {
        var picker = el('tColorPicker');
        if (! picker) { return; }
        picker.querySelectorAll('.ck-color-swatch').forEach(function (sw) {
            sw.classList.remove('ck-color-swatch--selected');
            var input = sw.querySelector('input[type="radio"]');
            if (input && input.value === colorKey) {
                sw.classList.add('ck-color-swatch--selected');
                input.checked = true;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        _initColorPicker();
    });

}());