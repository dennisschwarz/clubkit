/**
 * Member assignment modal for event tasks (task-tab).
 *
 * Replaces the old dual-listbox <select> approach with a SortableJS-driven
 * two-panel list:
 *   - Left  (#taskAssignAvailableList): draggable source (clone mode), not sortable.
 *   - Right (#taskAssignSortList):      sortable target, accepts drops from left.
 *
 * Each add/remove fires real-time AJAX. Sort order is persisted on drag end.
 * "Done" button closes the modal and triggers a tab-preserving page reload.
 *
 * @param {object} ctx - Shared context { cfg, csrf, Sortable, closest, reloadKeepingTab }
 */
export function initAssignModal(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var Sortable         = ctx.Sortable;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    var _taskAssignTaskId  = null;
    var _sortableAvail    = null;  // SortableJS-Instanz für die Verfügbarliste
    var _sortableAssigned = null;  // SortableJS-Instanz für die Zugewiesenen-Liste

    // ── DOM helpers ───────────────────────────────────────────────────────────

    function _availItem(memberId, name) {
        var li = document.createElement('li');
        li.className          = 'ck-assign-item';
        li.dataset.memberId   = String(memberId);
        var span              = document.createElement('span');
        span.className        = 'ck-assign-item__name';
        span.textContent      = name;
        li.appendChild(span);
        return li;
    }

    function _assignedItem(etmId, memberId, name) {
        var li = document.createElement('li');
        li.className        = 'ck-assign-item';
        li.dataset.etmId    = String(etmId);
        li.dataset.memberId = String(memberId);
        // No explicit drag handle — the whole item is the drag target.

        var nameSpan         = document.createElement('span');
        nameSpan.className   = 'ck-assign-item__name';
        nameSpan.textContent = name;

        var removeBtn        = document.createElement('button');
        removeBtn.type       = 'button';
        removeBtn.className  = 'ck-assign-item__remove';
        removeBtn.setAttribute('aria-label', 'Entfernen');
        removeBtn.textContent = '\u00d7';
        removeBtn.addEventListener('click', function () {
            _removeAssignment(li, etmId, memberId, name);
        });

        li.appendChild(nameSpan);
        li.appendChild(removeBtn);
        return li;
    }

    function _emptyState(listId) {
        var li       = document.createElement('li');
        li.className = 'ck-assign-list__empty';
        li.textContent = listId === 'taskAssignSortList'
            ? 'Noch niemand zugewiesen.'
            : 'Alle zugewiesen.';
        return li;
    }

    /**
     * Re-renders position badges (1, 2, 3 …) on every item in the assigned list.
     * Called after every structural change: open, drop, sort, remove.
     */
    function _refreshNumbers() {
        var sortList = document.getElementById('taskAssignSortList');
        if (! sortList) { return; }
        var items = sortList.querySelectorAll('.ck-assign-item[data-etm-id]');
        items.forEach(function (li, idx) {
            var badge = li.querySelector('.ck-assign-item__num');
            if (! badge) {
                badge = document.createElement('span');
                badge.className = 'ck-assign-item__num';
                badge.setAttribute('aria-hidden', 'true');
                li.insertBefore(badge, li.firstChild);
            }
            badge.textContent = String(idx + 1);
        });
    }

    function _refreshEmptyStates() {
        ['taskAssignAvailableList', 'taskAssignSortList'].forEach(function (id) {
            var list = document.getElementById(id);
            if (! list) { return; }
            var existing = list.querySelector('.ck-assign-list__empty');
            var hasItems = list.querySelectorAll('.ck-assign-item').length > 0;
            if (hasItems && existing) { existing.remove(); }
            if (! hasItems && ! existing) { list.appendChild(_emptyState(id)); }
        });
    }

    // ── Real-time AJAX: remove assignment ─────────────────────────────────────

    function _removeAssignment(li, etmId, memberId, name) {
        li.classList.add('ck-assign-item--pending');

        fetch(cfg.routes.membersBase + '/' + etmId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) {
            if (res.ok) {
                var availList = document.getElementById('taskAssignAvailableList');
                if (availList && memberId) {
                    availList.appendChild(_availItem(memberId, name));
                }
                li.remove();
                _refreshEmptyStates();
                _refreshNumbers();
            } else {
                li.classList.remove('ck-assign-item--pending');
                ckNotify('error', 'Fehler beim Entfernen der Zuweisung.');
            }
        })
        .catch(function () {
            li.classList.remove('ck-assign-item--pending');
            ckNotify('error', 'Netzwerkfehler.');
        });
    }

    // ── Real-time AJAX: reorder assigned list ─────────────────────────────────

    function _fireReorder() {
        var sortList = document.getElementById('taskAssignSortList');
        if (! sortList || ! _taskAssignTaskId) { return; }

        var ids = Array.from(sortList.querySelectorAll('.ck-assign-item[data-etm-id]'))
            .map(function (li) { return parseInt(li.dataset.etmId, 10); });

        if (ids.length === 0) { return; }

        fetch(cfg.routes.tasksBase + '/' + _taskAssignTaskId + '/members/reorder', {
            method:  'PATCH',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            body: JSON.stringify({ ids: ids }),
        });
        // Fire-and-forget: SortableJS already updated the DOM position.
    }

    // ── Open modal ────────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-assign-btn');
        if (! btn) { return; }

        _taskAssignTaskId = btn.dataset.taskId;

        var availList = document.getElementById('taskAssignAvailableList');
        var sortList  = document.getElementById('taskAssignSortList');
        var label     = document.getElementById('taskAssignLabel');
        if (! availList || ! sortList) { return; }

        if (label) { label.textContent = btn.dataset.taskName || ''; }

        availList.innerHTML = '';
        sortList.innerHTML  = '';

        // Collect assigned members from DOM (data-sort-order drives initial order).
        var row         = document.querySelector('.ck-task-row[data-task-id="' + _taskAssignTaskId + '"]');
        var assignedMap = {};

        if (row) {
            row.querySelectorAll('.ck-etm-remove-btn').forEach(function (rb) {
                var etmId    = rb.dataset.etmId;
                var memberId = String(rb.dataset.memberId);
                var name     = rb.dataset.memberName
                    || (cfg.members[memberId] ? cfg.members[memberId].name : '');
                var sortOrd  = parseInt(rb.dataset.sortOrder || '0', 10);
                if (memberId) {
                    assignedMap[memberId] = { etmId: etmId, memberId: memberId, name: name, sortOrder: sortOrd };
                }
            });
        }

        // Populate assigned list in sort_order.
        Object.values(assignedMap)
            .sort(function (a, b) { return a.sortOrder - b.sortOrder; })
            .forEach(function (a) {
                sortList.appendChild(_assignedItem(a.etmId, a.memberId, a.name));
            });

        // Populate available list (members not yet assigned).
        Object.keys(cfg.members).forEach(function (memberId) {
            if (! assignedMap[memberId]) {
                availList.appendChild(_availItem(memberId, cfg.members[memberId].name));
            }
        });

        _refreshEmptyStates();
        _refreshNumbers();

        if (Sortable) {
            // Bestehende Instanzen zerstören, bevor neue erstellt werden.
            // SortableJS erlaubt nur eine Instanz pro Element — beim zweiten
            // Öffnen des Modals würde create() sonst einen Fehler werfen.
            if (_sortableAvail)    { _sortableAvail.destroy();    _sortableAvail    = null; }
            if (_sortableAssigned) { _sortableAssigned.destroy(); _sortableAssigned = null; }

            // Available list: clone source, not sortable.
            _sortableAvail = Sortable.create(availList, {
                group:     { name: 'task-assign', pull: 'clone', put: false },
                sort:      false,
                animation: 120,
            });

            // Assigned list: sortable + accepts drops from available.
            // No handle option — the whole item is draggable.
            _sortableAssigned = Sortable.create(sortList, {
                group:     { name: 'task-assign', pull: false, put: true },
                animation: 150,

                onAdd: function (evt) {
                    // Member dragged from available → remove clone, fire AJAX.
                    var clone    = evt.item;
                    var memberId = clone.dataset.memberId;
                    if (! memberId) { clone.remove(); return; }

                    clone.remove();

                    var availItem = availList.querySelector('[data-member-id="' + memberId + '"]');
                    if (availItem) { availItem.remove(); }

                    var name = cfg.members[memberId] ? cfg.members[memberId].name : memberId;

                    var placeholder = _availItem(memberId, name);
                    placeholder.classList.add('ck-assign-item--pending');
                    sortList.appendChild(placeholder);
                    _refreshEmptyStates();

                    fetch(cfg.routes.membersBase, {
                        method:  'POST',
                        headers: {
                            'Content-Type':     'application/json',
                            'X-CSRF-TOKEN':     csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept':           'application/json',
                        },
                        body: JSON.stringify({
                            event_task_id: parseInt(_taskAssignTaskId, 10),
                            member_id:     parseInt(memberId, 10),
                        }),
                    })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        placeholder.remove();
                        if (data.success) {
                            var etmId = data.assignment.id;
                            sortList.appendChild(_assignedItem(etmId, memberId, name));
                            _refreshEmptyStates();
                            _refreshNumbers();
                            _fireReorder();
                        } else if (data.error === 'already_assigned') {
                            ckNotify('warning', 'Mitglied bereits zugewiesen.');
                            availList.appendChild(_availItem(memberId, name));
                        } else {
                            availList.appendChild(_availItem(memberId, name));
                            ckNotify('error', data.message || 'Fehler beim Zuweisen.');
                        }
                        _refreshEmptyStates();
                    })
                    .catch(function () {
                        placeholder.remove();
                        availList.appendChild(_availItem(memberId, name));
                        _refreshEmptyStates();
                        ckNotify('error', 'Netzwerkfehler.');
                    });
                },

                onUpdate: function () {
                    _refreshNumbers();
                    _fireReorder();
                },
            });
        }

        ckModalOpen('taskAssignModal');
    });

    // ── Done button: close + reload ───────────────────────────────────────────

    var doneBtn = document.getElementById('taskAssignDoneBtn');
    if (doneBtn) {
        doneBtn.addEventListener('click', function () {
            ckModalClose(null, 'taskAssignModal');
            reloadKeepingTab();
        });
    }
}