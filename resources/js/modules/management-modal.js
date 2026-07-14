/**
 * management-modal.js
 * Controls the Management tab: create/edit modals (AJAX save, no redirect),
 * delegated AJAX delete, inline member chip removal, and the member assign
 * modal (SortableJS drag & drop — same pattern as teams-modal.js).
 */

(function () {
    'use strict';

    var data    = window.CK_Management || {};
    var Sortable = window.Sortable || null;

    function el(id) { return document.getElementById(id); }
    function routes() { return data.routes || {}; }
    function csrf() { return data.csrf || ''; }

    // ── Fragment refresh (DOM-swap, no page reload) ────────────────────────────

    function _refreshFunctionsTab() {
        _fetchFragment(routes().functionsFragment, 'mgmtFunctionList');
    }

    function _refreshTasksTab() {
        _fetchFragment(routes().tasksFragment, 'mgmtTaskList');
    }

    function _fetchFragment(url, containerId) {
        if (! url) { return; }
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
        })
        .then(function (res) { return res.text(); })
        .then(function (html) {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            var container = el(containerId);
            if (container) {
                container.innerHTML = html;
                _initMgmtSortable();
            }
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
        });
    }

    // ── SortableJS: drag & drop between team sections ─────────────────────────

    function _onMgmtDrop(evt, type) {
        var row       = evt.item;
        var itemId    = parseInt(row.dataset.id, 10);
        var fromTbody = evt.from;
        var toTbody   = evt.to;

        if (fromTbody === toTbody) { return; } // reorder within same section — no AJAX

        // Remove the empty-state row from the target tbody when a real row arrives.
        var toEmptyTd = toTbody.querySelector('td.ck-empty-state');
        if (toEmptyTd) { toEmptyTd.closest('tr').remove(); }

        // If the source tbody has no real rows left, insert an empty-state row.
        // .ck-mgmt-real-row is only on real data rows — never on the empty-state row.
        if (fromTbody.querySelectorAll('.ck-mgmt-real-row').length === 0) {
            var emptyRow       = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="5" class="ck-empty-state">–</td>';
            fromTbody.appendChild(emptyRow);
        }

        var rawTeamId = toTbody.dataset.teamId;
        var teamId    = (rawTeamId && rawTeamId !== 'allgemein')
            ? parseInt(rawTeamId, 10)
            : null;

        var base = type === 'function'
            ? routes().functionMove + '/' + itemId + '/move'
            : routes().taskMove    + '/' + itemId + '/move';

        _fetch(base, 'PATCH', { team_id: teamId })
            .then(function (resp) {
                if (! resp.success && typeof window.ckNotify === 'function') {
                    window.ckNotify('error', 'Fehler beim Verschieben.');
                }
            })
            .catch(function () {
                if (typeof window.ckNotify === 'function') {
                    window.ckNotify('error', 'Netzwerkfehler beim Verschieben.');
                }
            });
    }

    var _mgmtFnSortableOptions = {
        group:      { name: 'mgmt-functions', pull: true, put: true },
        draggable:  '.ck-mgmt-real-row',   // exclude empty-state rows from drag
        handle:     'td:not(.ck-table__actions)',
        animation:  150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function (evt) { _onMgmtDrop(evt, 'function'); },
    };

    var _mgmtTaskSortableOptions = {
        group:      { name: 'mgmt-tasks', pull: true, put: true },
        draggable:  '.ck-mgmt-real-row',   // exclude empty-state rows from drag
        handle:     'td:not(.ck-table__actions)',
        animation:  150,
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function (evt) { _onMgmtDrop(evt, 'task'); },
    };

    function _initMgmtSortable() {
        if (! Sortable) { return; }
        document.querySelectorAll('.ck-mgmt-fn-sortable:not([data-sortable-init])').forEach(function (tbody) {
            tbody.setAttribute('data-sortable-init', '1');
            Sortable.create(tbody, _mgmtFnSortableOptions);
        });
        document.querySelectorAll('.ck-mgmt-task-sortable:not([data-sortable-init])').forEach(function (tbody) {
            tbody.setAttribute('data-sortable-init', '1');
            Sortable.create(tbody, _mgmtTaskSortableOptions);
        });
    }

    document.addEventListener('DOMContentLoaded', function () { _initMgmtSortable(); });

    // ── AJAX helpers ──────────────────────────────────────────────────────────

    function _fetch(url, method, body) {
        var opts = {
            method:  method,
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf(),
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
        };
        if (body) { opts.body = JSON.stringify(body); }
        return fetch(url, opts).then(function (res) { return res.json(); });
    }

    // ── Delegated event handlers ──────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target : null;
        if (! btn) { return; }

        // Edit button.
        var editBtn = btn.closest('[data-mgmt-edit]');
        if (editBtn) {
            var type = editBtn.dataset.mgmtEdit;
            mgmtModalOpen(type, 'edit', parseInt(editBtn.dataset.id, 10));
            return;
        }

        // Assign button.
        var assignBtn = btn.closest('[data-mgmt-assign]');
        if (assignBtn) {
            openMgmtAssign(
                assignBtn.dataset.mgmtAssign,
                parseInt(assignBtn.dataset.id, 10),
                assignBtn.dataset.name || ''
            );
            return;
        }

        // Delete button.
        var deleteBtn = btn.closest('[data-mgmt-delete]');
        if (deleteBtn) {
            var confirmMsg = deleteBtn.dataset.ckConfirm || 'Wirklich löschen?';
            var type2      = deleteBtn.dataset.mgmtDelete;
            var id2        = parseInt(deleteBtn.dataset.id, 10);
            if (typeof window.ckConfirm === 'function') {
                window.ckConfirm(confirmMsg, function () { _deleteItem(type2, id2); });
            } else if (window.confirm(confirmMsg)) {
                _deleteItem(type2, id2);
            }
            return;
        }

        // Inline member chip × remove.
        var removeBtn = btn.closest('.ck-mgmt-remove-member');
        if (removeBtn) {
            var parentType = removeBtn.dataset.type;
            var parentId   = parseInt(removeBtn.dataset.parentId, 10);
            var memberId   = parseInt(removeBtn.dataset.memberId, 10);
            _removeInlineMember(parentType, parentId, memberId, removeBtn.closest('.ck-task-member'));
        }
    });

    function _deleteItem(type, id) {
        var base = type === 'function' ? routes().functionDelete : routes().taskDelete;
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }
        _fetch(base + '/' + id, 'DELETE')
            .then(function (resp) {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                if (resp.success) {
                    type === 'function' ? _refreshFunctionsTab() : _refreshTasksTab();
                }
            })
            .catch(function () {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            });
    }

    function _removeInlineMember(type, parentId, memberId, chip) {
        if (chip) { chip.classList.add('ck-assign-item--pending'); }
        var base = type === 'function'
            ? routes().functionMemberBase + '/' + parentId + '/members/' + memberId
            : routes().taskMemberBase    + '/' + parentId + '/members/' + memberId;
        _fetch(base, 'DELETE')
            .then(function (resp) {
                if (resp.success && chip) { chip.remove(); }
                else if (chip) { chip.classList.remove('ck-assign-item--pending'); }
            })
            .catch(function () {
                if (chip) { chip.classList.remove('ck-assign-item--pending'); }
            });
    }

    // ── Form submit (AJAX, no redirect) ───────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        ['mgmtFunctionForm', 'mgmtTaskForm'].forEach(function (formId) {
            var form = el(formId);
            if (! form) { return; }
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                // Stop the global app.js submit handler from disabling the buttons —
                // this is an AJAX form; we manage the button state ourselves.
                e.stopImmediatePropagation();
                _submitMgmtForm(form);
            });
        });

        // Done button for assign modal.
        var doneBtn = el('mgmtAssignDoneBtn');
        if (doneBtn) {
            doneBtn.addEventListener('click', function () {
                ckModalClose(null, 'mgmtAssignModal');
                if (_assignType === 'function') { _refreshFunctionsTab(); }
                else                            { _refreshTasksTab(); }
            });
        }
    });

    function _submitMgmtForm(form) {
        var fd         = new FormData(form);
        var method     = (fd.get('_method') || 'POST').toUpperCase();
        var url        = form.action;
        var submitBtns = form.querySelectorAll('[type="submit"]');
        var payload    = {};
        fd.forEach(function (v, k) {
            if (k === '_token' || k === '_method') { return; }
            if (k.endsWith('[]')) {
                var key = k.slice(0, -2);
                payload[key] = payload[key] || [];
                payload[key].push(v);
            } else {
                payload[k] = v;
            }
        });

        // Disable during request to prevent double-submit.
        submitBtns.forEach(function (b) { b.disabled = true; });

        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }
        _fetch(url, method, payload)
            .then(function (resp) {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                if (resp.success || resp.id) {
                    var isFunction = form.id === 'mgmtFunctionForm';
                    ckModalClose(null, isFunction ? 'mgmtFunctionModal' : 'mgmtTaskModal');
                    if (isFunction) { _refreshFunctionsTab(); }
                    else            { _refreshTasksTab(); }
                } else {
                    // Server returned an error — re-enable so the user can correct and retry.
                    submitBtns.forEach(function (b) { b.disabled = false; });
                }
            })
            .catch(function () {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                submitBtns.forEach(function (b) { b.disabled = false; });
            });
    }

    // ── Assign modal (SortableJS — same pattern as teams-modal.js) ────────────

    var _assignType  = null;  // 'function' | 'task'
    var _assignId    = null;
    var _sortableAvail    = null;
    var _sortableAssigned = null;

    function _memberItem(memberId, name, isAssigned) {
        var li            = document.createElement('li');
        li.className      = 'ck-assign-item';
        li.dataset.memberId = String(memberId);

        var nameSpan      = document.createElement('span');
        nameSpan.className = 'ck-assign-item__name';
        nameSpan.textContent = name;
        li.appendChild(nameSpan);

        if (isAssigned) {
            var removeBtn       = document.createElement('button');
            removeBtn.type      = 'button';
            removeBtn.className = 'ck-assign-item__remove';
            removeBtn.setAttribute('aria-label', 'Entfernen');
            removeBtn.textContent = '\u00d7';
            removeBtn.addEventListener('click', function () {
                _assignRemoveMember(li, memberId);
            });
            li.appendChild(removeBtn);
        }
        return li;
    }

    function _assignEmptyState(which) {
        var li       = document.createElement('li');
        li.className = 'ck-assign-list__empty';
        li.textContent = which === 'avail'
            ? 'Alle Mitglieder bereits zugewiesen.'
            : 'Noch kein Mitglied zugewiesen.';
        return li;
    }

    function _assignRefreshEmpty() {
        ['mgmtAssignAvailList', 'mgmtAssignedList'].forEach(function (id) {
            var list = el(id);
            if (! list) { return; }
            var existing = list.querySelector('.ck-assign-list__empty');
            var hasItems = list.querySelectorAll('.ck-assign-item').length > 0;
            if (hasItems && existing)  { existing.remove(); }
            if (! hasItems && ! existing) {
                list.appendChild(_assignEmptyState(id === 'mgmtAssignAvailList' ? 'avail' : 'assigned'));
            }
        });
    }

    window.openMgmtAssign = function (type, id, name) {
        _assignType = type;
        _assignId   = id;

        var titleEl = el('mgmtAssignModalTitle');
        if (titleEl) { titleEl.textContent = (type === 'function' ? 'Funktion: ' : 'Aufgabe: ') + name; }

        var allMembers      = data.members || {};
        var currentItem     = (type === 'function' ? data.functions : data.tasks)[id] || {};
        var assignedIds     = new Set((currentItem.member_ids || []).map(Number));

        var availList    = el('mgmtAssignAvailList');
        var assignedList = el('mgmtAssignedList');
        if (! availList || ! assignedList) { return; }

        availList.innerHTML   = '';
        assignedList.innerHTML = '';

        Object.values(allMembers).forEach(function (m) {
            if (assignedIds.has(m.id)) {
                assignedList.appendChild(_memberItem(m.id, m.name, true));
            } else {
                availList.appendChild(_memberItem(m.id, m.name, false));
            }
        });

        _assignRefreshEmpty();

        if (_sortableAvail)    { _sortableAvail.destroy();    _sortableAvail    = null; }
        if (_sortableAssigned) { _sortableAssigned.destroy(); _sortableAssigned = null; }

        if (Sortable) {
            _sortableAvail = Sortable.create(availList, {
                group:     { name: 'mgmt-assign', pull: 'clone', put: false },
                sort:      false,
                animation: 120,
            });

            _sortableAssigned = Sortable.create(assignedList, {
                group:     { name: 'mgmt-assign', pull: false, put: true },
                sort:      true,
                animation: 150,
                onAdd: function (evt) {
                    var clone    = evt.item;
                    var memberId = parseInt(clone.dataset.memberId, 10);
                    var nameEl   = clone.querySelector('.ck-assign-item__name');
                    var memberName = nameEl ? nameEl.textContent : String(memberId);
                    clone.remove();

                    var src = availList.querySelector('[data-member-id="' + memberId + '"]');
                    if (src) { src.remove(); }

                    var placeholder = _memberItem(memberId, memberName, true);
                    placeholder.classList.add('ck-assign-item--pending');
                    assignedList.appendChild(placeholder);
                    _assignRefreshEmpty();

                    _assignAddMember(memberId, memberName, placeholder);
                },
            });
        }

        ckModalOpen('mgmtAssignModal');
    };

    function _assignAddMember(memberId, name, placeholder) {
        var base = _assignType === 'function'
            ? routes().functionMemberBase + '/' + _assignId + '/members'
            : routes().taskMemberBase     + '/' + _assignId + '/members';

        _fetch(base, 'POST', { member_id: memberId })
            .then(function (resp) {
                placeholder.classList.remove('ck-assign-item--pending');
                if (! resp.success) {
                    placeholder.remove();
                    var availList = el('mgmtAssignAvailList');
                    if (availList) { availList.appendChild(_memberItem(memberId, name, false)); }
                    _assignRefreshEmpty();
                }
            })
            .catch(function () {
                placeholder.classList.remove('ck-assign-item--pending');
                placeholder.remove();
                _assignRefreshEmpty();
            });
    }

    function _assignRemoveMember(li, memberId) {
        li.classList.add('ck-assign-item--pending');
        var base = _assignType === 'function'
            ? routes().functionMemberBase + '/' + _assignId + '/members/' + memberId
            : routes().taskMemberBase     + '/' + _assignId + '/members/' + memberId;

        _fetch(base, 'DELETE')
            .then(function (resp) {
                if (resp.success) {
                    var nameEl     = li.querySelector('.ck-assign-item__name');
                    var memberName = nameEl ? nameEl.textContent : String(memberId);
                    var availList  = el('mgmtAssignAvailList');
                    if (availList) { availList.appendChild(_memberItem(memberId, memberName, false)); }
                    li.remove();
                    _assignRefreshEmpty();
                } else {
                    li.classList.remove('ck-assign-item--pending');
                }
            })
            .catch(function () {
                li.classList.remove('ck-assign-item--pending');
            });
    }

    // ── Modal open (create / edit) ────────────────────────────────────────────

    // teamId is passed by the "+" buttons in section headers so that a newly
    // created item is immediately assigned to the correct team section.
    window.mgmtModalOpen = function (type, mode, id, teamId) {
        id     = id     || null;
        teamId = teamId || null;
        var isFunction = type === 'function';
        var modalId    = isFunction ? 'mgmtFunctionModal' : 'mgmtTaskModal';
        var formId     = isFunction ? 'mgmtFunctionForm'  : 'mgmtTaskForm';
        var methodId   = isFunction ? 'mgmtFunctionFormMethod' : 'mgmtTaskFormMethod';
        var teamFldId  = isFunction ? 'mgmtFunctionTeamId'     : 'mgmtTaskTeamId';
        var store      = isFunction ? routes().functionStore   : routes().taskStore;
        var update     = isFunction ? routes().functionUpdate  : routes().taskUpdate;

        var form       = el(formId);
        var methodEl   = el(methodId);
        if (! form || ! methodEl) { return; }

        form.reset();
        form.querySelectorAll('[type="submit"]').forEach(function (b) { b.disabled = false; });
        var titleEl = document.querySelector('#' + modalId + ' .ck-modal__title');

        if (mode === 'create') {
            if (titleEl) { titleEl.textContent = isFunction ? 'Funktion anlegen' : 'Aufgabe anlegen'; }
            methodEl.value = 'POST';
            form.action    = store;
            // Pre-set the target team so the controller assigns to the correct section.
            var teamFld = el(teamFldId);
            if (teamFld) { teamFld.value = teamId || ''; }
            ckModalOpen(modalId);

        } else if (mode === 'edit' && id) {
            var item = (isFunction ? data.functions : data.tasks)[id];
            if (! item) { return; }
            if (titleEl) { titleEl.textContent = '\u201e' + item.name + '\u201c bearbeiten'; }
            methodEl.value = 'PATCH';
            form.action    = update + '/' + id;
            // Clear team_id — team assignment in edit mode is handled via drag & drop only.
            var teamFldEdit = el(teamFldId);
            if (teamFldEdit) { teamFldEdit.value = ''; }

            _setField(isFunction ? 'mgmtFunctionFieldName' : 'mgmtTaskFieldName', item.name);

            if (! isFunction) {
                _setField('mgmtTaskFieldDesc',     item.description || '');
                _setField('mgmtTaskFieldPriority', item.priority    || 'normal');
                _setField('mgmtTaskFieldCategory', item.category_id || '');
            }

            ckModalOpen(modalId);
            ckEmit('management.' + type + '.modal.open', { mode: 'edit', id: id, item: item });
        }
    };

    // ── Private helpers ───────────────────────────────────────────────────────

    function _setField(id, value) {
        var input = el(id);
        if (input) { input.value = value; }
    }

}());