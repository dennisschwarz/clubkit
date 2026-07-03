/**
 * ClubKit – Event Detail Page
 *
 * Handles:
 *   - Tab switching (ckEvtTab — global, outside IIFE)
 *   - Task completion toggle (AJAX PATCH, no page reload)
 *   - Task add (AJAX POST, page reload after success)
 *   - Task remove (AJAX DELETE, page reload after success)
 *   - Slot add (AJAX POST, page reload after success)
 *   - Slot remove (AJAX DELETE, page reload after success)
 *   - Progress badge + progress bar update after checkbox change
 *
 * Rules:
 *   - No el.style.property assignments → use classList only
 *   - CSS custom properties set via setProperty() (required for dynamic progress bar width)
 *   - All fetch() calls use window.CK_EventDetail for config
 */

/**
 * Switches the active event detail tab and toggles the contextual header action button.
 * Global (outside IIFE) so onclick="ckEvtTab(...)" in show.blade.php can reach it.
 *
 * @param {string}      tabId  Pane suffix (e.g. 'tasks', 'slots', 'functions', 'teams')
 * @param {HTMLElement} btn    The clicked tab button
 */
window.ckEvtTab = function (tabId, btn) {
    // Deactivate all panes (now use .ck-local-section, same as Management/Treasury)
    document.querySelectorAll('.ck-local-section').forEach(function (pane) {
        pane.classList.remove('ck-local-section--active');
    });
    // Deactivate all tab buttons (now use .ck-local-tab)
    document.querySelectorAll('.ck-local-tab').forEach(function (b) {
        b.classList.remove('ck-local-tab--active');
    });
    // Deactivate all header action buttons (kept for optional future use)
    document.querySelectorAll('.ck-event-tab-action').forEach(function (a) {
        a.classList.remove('ck-event-tab-action--active');
    });

    // Activate target pane, tab button and (optionally) header action button
    var pane   = document.getElementById('ckEvtPane-' + tabId);
    var action = document.getElementById('ckEvtAction-' + tabId);
    if (pane)   { pane.classList.add('ck-local-section--active'); }
    if (btn)    { btn.classList.add('ck-local-tab--active'); }
    if (action) { action.classList.add('ck-event-tab-action--active'); }
};

(function () {
    'use strict';

    const cfg = window.CK_EventDetail;
    if (! cfg) { return; }

    const csrf = cfg.csrf;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the closest ancestor (or self) matching the given selector.
     * @param {Element} el
     * @param {string} selector
     * @returns {Element|null}
     */
    function closest(el, selector) {
        while (el && ! el.matches(selector)) {
            el = el.parentElement;
        }
        return el || null;
    }

    /**
     * Updates the section badge text and color class after a checkbox change.
     * @param {string} sectionSlug
     */
    function updateSectionBadge(sectionSlug) {
        const section = document.querySelector('[data-section="' + sectionSlug + '"]');
        if (! section) { return; }

        const rows  = section.querySelectorAll('.ck-task-row');
        const done  = section.querySelectorAll('.ck-task-row--done').length;
        const total = rows.length;

        const badge = document.querySelector('[data-section-badge="' + sectionSlug + '"]');
        if (badge) {
            badge.textContent = done + '/' + total;
            badge.classList.remove('ck-badge--green', 'ck-badge--amber', 'ck-badge--gray');
            if (done === total) {
                badge.classList.add('ck-badge--green');
            } else if (done > 0) {
                badge.classList.add('ck-badge--amber');
            } else {
                badge.classList.add('ck-badge--gray');
            }
        }
    }

    /**
     * Updates the global progress bar and counter.
     * Uses setProperty() to write the CSS custom property --progress
     * (the only permitted el.style.* usage: CSS variable, not a presentation property).
     */
    function updateGlobalProgress() {
        const allRows  = document.querySelectorAll('.ck-task-row');
        const doneRows = document.querySelectorAll('.ck-task-row--done');
        const total    = allRows.length;
        const done     = doneRows.length;

        const counter = document.getElementById('global-done-count');
        if (counter) {
            counter.textContent = String(done);
        }

        const bar = document.querySelector('.ck-event-progress__fill');
        if (bar && total > 0) {
            const pct = Math.round(done / total * 100);
            bar.style.setProperty('--progress', pct + '%');
        }
    }

    // ── Task Checkbox (complete toggle) ───────────────────────────────────────

    document.addEventListener('change', function (e) {
        if (! e.target.matches('.ck-task-checkbox')) { return; }

        const checkbox  = e.target;
        const taskId    = checkbox.dataset.taskId;
        const completed = checkbox.checked;
        const row       = closest(checkbox, '.ck-task-row');
        const section   = row ? row.dataset.section : null;

        // Optimistic UI update
        if (row) {
            if (completed) {
                row.classList.add('ck-task-row--done');
            } else {
                row.classList.remove('ck-task-row--done');
            }
        }

        if (section) { updateSectionBadge(section); }
        updateGlobalProgress();

        const url = cfg.routes.tasksBase + '/' + taskId + '/complete';

        fetch(url, {
            method:  'PATCH',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ completed: completed }),
        })
        .then(function (res) {
            if (! res.ok) {
                // Revert on failure
                checkbox.checked = ! completed;
                if (row) {
                    if (! completed) {
                        row.classList.add('ck-task-row--done');
                    } else {
                        row.classList.remove('ck-task-row--done');
                    }
                }
                if (section) { updateSectionBadge(section); }
                updateGlobalProgress();
            }
        })
        .catch(function () {
            // Revert on network error
            checkbox.checked = ! completed;
        });
    });

    // ── Add Task ──────────────────────────────────────────────────────────────

    const addBtn    = document.getElementById('addTaskBtn');
    const addSelect = document.getElementById('addTaskSelect');

    if (addBtn && addSelect) {
        addBtn.addEventListener('click', function () {
            const taskId = addSelect.value;
            if (! taskId) {
                addSelect.classList.add('ck-input--error');
                return;
            }
            addSelect.classList.remove('ck-input--error');
            addBtn.disabled = true;

            fetch(cfg.routes.tasksBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ task_id: parseInt(taskId, 10) }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Fehler beim Hinzufügen der Aufgabe.');
                    addBtn.disabled = false;
                }
            })
            .catch(function () {
                alert('Netzwerkfehler. Bitte Seite neu laden.');
                addBtn.disabled = false;
            });
        });
    }

    // ── Remove Task ───────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        const btn = closest(e.target, '.ck-task-remove-btn');
        if (! btn) { return; }

        const taskId = btn.dataset.taskId;
        if (! taskId) { return; }

        btn.disabled = true;

        fetch(cfg.routes.tasksBase + '/' + taskId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                btn.disabled = false;
            }
        })
        .catch(function () {
            btn.disabled = false;
        });
    });

    // ── Populate selects from CK_EventDetail data ─────────────────────────────

    /**
     * Appends <option> elements to a <select> from a data map.
     * @param {string}  selId       ID of the <select> element
     * @param {object}  dataMap     key → {id, name} map
     * @param {string}  placeholder Placeholder option text (empty value)
     */
    function populateSelect(selId, dataMap, placeholder) {
        var sel = document.getElementById(selId);
        if (! sel) { return; }
        // Reset to placeholder only
        sel.innerHTML = '';
        var ph    = document.createElement('option');
        ph.value  = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        Object.keys(dataMap).forEach(function (id) {
            var opt       = document.createElement('option');
            opt.value     = id;
            opt.textContent = dataMap[id].name;
            sel.appendChild(opt);
        });
    }

    // newTaskModal: category dropdown (from CK_EventDetail.categories)
    if (cfg.categories) {
        populateSelect('newTaskCategoryId', cfg.categories, '– Keine Kategorie –');
    }

    // newTaskModal: member dropdown (from CK_EventDetail.members)
    populateSelect('newTaskMemberId', cfg.members, '– Kein Mitglied –');

    // slotModal: task dropdown (from CK_EventDetail.einsatzplanTasks)
    if (cfg.einsatzplanTasks) {
        populateSelect('slotModalTaskId', cfg.einsatzplanTasks, '– Aufgabe wählen –');
    }

    // slotModal: member dropdown (from CK_EventDetail.members)
    populateSelect('slotModalMemberId', cfg.members, '– Person wählen –');

    // Inline member assign selects per task row (Aufgaben-Tab)
    // Reads from CK_EventDetail.members — no extra Blade variable needed.
    document.querySelectorAll('.ck-task-assign-select').forEach(function (sel) {
        Object.keys(cfg.members).forEach(function (id) {
            var opt         = document.createElement('option');
            opt.value       = id;
            opt.textContent = cfg.members[id].name;
            sel.appendChild(opt);
        });
    });

    // Inline member assign selects per function card (Funktionen-Tab)
    // Reads from CK_EventDetail.members — pre-selects current assignment via data-current-member-id.
    document.querySelectorAll('.ck-func-assign-select').forEach(function (sel) {
        var currentId = sel.dataset.currentMemberId || '';
        Object.keys(cfg.members).forEach(function (id) {
            var opt         = document.createElement('option');
            opt.value       = id;
            opt.textContent = cfg.members[id].name;
            if (id === currentId) { opt.selected = true; }
            sel.appendChild(opt);
        });
    });

    // ── Assign member inline (Aufgaben-Tab) ───────────────────────────────────

    document.addEventListener('change', function (e) {
        if (! e.target.matches('.ck-task-assign-select')) { return; }

        var sel      = e.target;
        var memberId = sel.value;
        var taskId   = sel.dataset.taskId;
        if (! memberId || ! taskId) { return; }

        sel.disabled = true;

        fetch(cfg.routes.membersBase, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                task_id:   parseInt(taskId,   10),
                member_id: parseInt(memberId, 10),
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                sel.value    = '';
                sel.disabled = false;
            }
        })
        .catch(function () {
            sel.value    = '';
            sel.disabled = false;
        });
    });

    // ── Remove member assignment (Aufgaben-Tab) ───────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-etm-remove-btn');
        if (! btn) { return; }

        var etmId = btn.dataset.etmId;
        if (! etmId) { return; }

        btn.disabled = true;

        fetch(cfg.routes.membersBase + '/' + etmId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                btn.disabled = false;
            }
        })
        .catch(function () {
            btn.disabled = false;
        });
    });

    // ── Initial progress bar render ───────────────────────────────────────────

    updateGlobalProgress();

    // ── Category progress bars (Übersicht-Tab) ────────────────────────────────
    // Uses setProperty() for CSS custom property — only allowed pattern for dynamic widths.

    document.querySelectorAll('.ck-cat-progress__fill').forEach(function (fill) {
        fill.style.setProperty('--progress', (fill.dataset.progress || '0') + '%');
    });

    // ── New Task Modal submit (Tab 2: Aufgaben → Dropdown → "Neue Aufgabe") ──────
    // Two-step flow: 1) create global ManagementTask, 2) assign to event (+ member).

    var newTaskBtn = document.getElementById('newTaskSubmitBtn');

    if (newTaskBtn) {
        newTaskBtn.addEventListener('click', function () {
            var nameInput    = document.getElementById('newTaskName');
            var catSelect    = document.getElementById('newTaskCategoryId');
            var prioSelect   = document.getElementById('newTaskPriority');
            var deadlineInput= document.getElementById('newTaskDeadline');
            var memberSelect = document.getElementById('newTaskMemberId');

            var name = nameInput ? nameInput.value.trim() : '';
            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            newTaskBtn.disabled = true;

            var taskBody = { name: name };
            if (catSelect   && catSelect.value)   { taskBody.category_id = parseInt(catSelect.value, 10); }
            if (prioSelect  && prioSelect.value)  { taskBody.priority    = prioSelect.value; }

            // Step 1: create the global ManagementTask via management/tasks
            fetch(cfg.routes.mgmtTasksBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify(taskBody),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (! data.success) {
                    ckNotify('error', data.message || 'Fehler beim Anlegen der Aufgabe.');
                    newTaskBtn.disabled = false;
                    return;
                }

                var assignBody = { task_id: data.id };
                if (deadlineInput && deadlineInput.value) {
                    assignBody.deadline_at = deadlineInput.value;
                }

                // Step 2: assign the task to this event
                return fetch(cfg.routes.tasksBase, {
                    method:  'POST',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'application/json',
                    },
                    body: JSON.stringify(assignBody),
                })
                .then(function (res) { return res.json(); })
                .then(function (assignData) {
                    if (! assignData.success) {
                        ckNotify('error', assignData.message || 'Fehler beim Zuweisen der Aufgabe.');
                        newTaskBtn.disabled = false;
                        return;
                    }

                    // Optional step 3: assign selected member (time_from = null → Aufgaben-Tab)
                    if (memberSelect && memberSelect.value) {
                        fetch(cfg.routes.membersBase, {
                            method:  'POST',
                            headers: {
                                'Content-Type':     'application/json',
                                'X-CSRF-TOKEN':     csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept':           'application/json',
                            },
                            body: JSON.stringify({
                                task_id:   data.id,
                                member_id: parseInt(memberSelect.value, 10),
                            }),
                        })
                        .then(function () { window.location.reload(); })
                        .catch(function () { window.location.reload(); });
                    } else {
                        window.location.reload();
                    }
                });
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newTaskBtn.disabled = false;
            });
        });
    }

    // ── New Category Modal submit (Tab 2: Aufgaben → Dropdown → "Neue Kategorie") ─

    var newCatBtn = document.getElementById('newCatSubmitBtn');

    if (newCatBtn) {
        newCatBtn.addEventListener('click', function () {
            var nameInput = document.getElementById('newCatName');
            var name      = nameInput ? nameInput.value.trim() : '';
            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            newCatBtn.disabled = true;

            fetch(cfg.routes.categoriesBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ name: name }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    // Add the new category to the newTaskModal category select
                    var catSel = document.getElementById('newTaskCategoryId');
                    if (catSel) {
                        var opt         = document.createElement('option');
                        opt.value       = data.id;
                        opt.textContent = data.name;
                        catSel.appendChild(opt);
                        catSel.value    = data.id;
                    }
                    // Update local cache for future populateSelect calls
                    if (cfg.categories) {
                        cfg.categories[data.id] = { id: data.id, name: data.name };
                    }
                    ckModalClose(null, 'newCatModal');
                    ckNotify('success', 'Kategorie „' + data.name + '" angelegt.');
                    newCatBtn.disabled = false;
                    if (nameInput) { nameInput.value = ''; }
                } else {
                    ckNotify('error', data.message || 'Fehler beim Anlegen der Kategorie.');
                    newCatBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newCatBtn.disabled = false;
            });
        });
    }

    // ── Slot Modal submit (Tab 3: Einsatzplan → "Einsatz zuweisen") ───────────
    // Replaces the former addSlotBtn handler which referenced now-removed inline form fields.

    var slotModalBtn = document.getElementById('slotModalSubmitBtn');

    if (slotModalBtn) {
        slotModalBtn.addEventListener('click', function () {
            var taskSel  = document.getElementById('slotModalTaskId');
            var memSel   = document.getElementById('slotModalMemberId');
            var fromInp  = document.getElementById('slotModalTimeFrom');
            var toInp    = document.getElementById('slotModalTimeTo');

            var taskId   = taskSel  ? taskSel.value  : '';
            var memberId = memSel   ? memSel.value   : '';
            var timeFrom = fromInp  ? fromInp.value  : '';
            var timeTo   = toInp    ? toInp.value    : '';

            // Client-side validation
            var hasError = false;
            [taskSel, memSel, fromInp, toInp].forEach(function (el) {
                if (el) {
                    if (! el.value) {
                        el.classList.add('ck-input--error');
                        hasError = true;
                    } else {
                        el.classList.remove('ck-input--error');
                    }
                }
            });
            if (hasError) { return; }

            slotModalBtn.disabled = true;

            fetch(cfg.routes.slotsBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({
                    task_id:   parseInt(taskId,   10),
                    member_id: parseInt(memberId, 10),
                    time_from: timeFrom,
                    time_to:   timeTo,
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Speichern des Einsatzes.');
                    slotModalBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                slotModalBtn.disabled = false;
            });
        });
    }

    // ── Populate function select from available functions (Funktionen-Tab modal) ──

    (function populateFuncSelect() {
        var funcSel = document.getElementById('newFuncSelect');
        if (! funcSel) { return; }
        var available = cfg.availableFunctions;
        if (! available) { return; }
        Object.values(available).forEach(function (fn) {
            var opt         = document.createElement('option');
            opt.value       = fn.id;
            opt.textContent = fn.name;
            funcSel.appendChild(opt);
        });
    }());

    // ── Add function to event (Funktionen-Tab: "Funktion hinzufügen" button) ────

    var newFuncBtn = document.getElementById('newFuncSubmitBtn');

    if (newFuncBtn) {
        newFuncBtn.addEventListener('click', function () {
            var funcSel    = document.getElementById('newFuncSelect');
            var functionId = funcSel ? funcSel.value : '';
            if (! functionId) {
                if (funcSel) { funcSel.classList.add('ck-input--error'); }
                return;
            }
            if (funcSel) { funcSel.classList.remove('ck-input--error'); }
            newFuncBtn.disabled = true;

            // POST /events/{event}/functions — assigns a global function to this event
            fetch(cfg.routes.funcAddBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ function_id: parseInt(functionId, 10) }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Hinzufügen der Funktion.');
                    newFuncBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newFuncBtn.disabled = false;
            });
        });
    }

    // ── Remove function from event (Funktionen-Tab: × button) ──────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-func-remove-btn');
        if (! btn) { return; }

        var functionId = btn.dataset.functionId;
        if (! functionId) { return; }

        var confirmMsg = btn.dataset.ckConfirm;

        function doRemoveFunc() {
            btn.disabled = true;
            fetch(cfg.routes.funcAssignBase + '/' + functionId, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Entfernen der Funktion.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                btn.disabled = false;
            });
        }

        if (confirmMsg) {
            window.ckConfirm(confirmMsg, doRemoveFunc);
        } else {
            doRemoveFunc();
        }
    });

    // ── Assign member to function (Funktionen-Tab) ───────────────────────────

    document.addEventListener('change', function (e) {
        if (! e.target.matches('.ck-func-assign-select')) { return; }

        var sel        = e.target;
        var memberId   = sel.value;
        var functionId = sel.dataset.functionId;
        if (! functionId) { return; }

        sel.disabled = true;

        fetch(cfg.routes.funcAssignBase + '/' + functionId, {
            method:  'PATCH',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            body: JSON.stringify({
                member_id: memberId ? parseInt(memberId, 10) : null,
            }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                ckNotify('error', data.message || 'Fehler beim Zuweisen der Person.');
                sel.disabled = false;
            }
        })
        .catch(function () {
            ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
            sel.disabled = false;
        });
    });

    // ── Remove Slot (Einsatzplan-Tab) ─────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-slot-remove-btn');
        if (!btn) { return; }
        var slotId = btn.dataset.slotId;
        if (!slotId) { return; }
        btn.disabled = true;
        fetch(cfg.routes.slotsBase + '/' + slotId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { window.location.reload(); }
            else              { btn.disabled = false; }
        })
        .catch(function () { btn.disabled = false; });
    });

    // ── Add team to event (Teams-Tab: "Team hinzufügen" button) ─────────────

    var teamAddBtn = document.getElementById('teamAddBtn');

    if (teamAddBtn && cfg.routes.teamsBase) {
        teamAddBtn.addEventListener('click', function () {
            var teamSel = document.getElementById('teamAddSelect');
            var teamId  = teamSel ? teamSel.value : '';
            if (! teamId) {
                if (teamSel) { teamSel.classList.add('ck-input--error'); }
                return;
            }
            if (teamSel) { teamSel.classList.remove('ck-input--error'); }
            teamAddBtn.disabled = true;

            fetch(cfg.routes.teamsBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ team_id: parseInt(teamId, 10) }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Hinzufügen des Teams.');
                    teamAddBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                teamAddBtn.disabled = false;
            });
        });
    }

    // ── Remove team from event (Teams-Tab: × button) ─────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-team-remove-btn');
        if (! btn) { return; }

        var teamId = btn.dataset.teamId;
        if (! teamId) { return; }

        var confirmMsg = btn.dataset.ckConfirm;

        function doRemoveTeam() {
            btn.disabled = true;
            fetch(cfg.routes.teamsBase + '/' + teamId, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Entfernen des Teams.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                btn.disabled = false;
            });
        }

        if (confirmMsg) {
            window.ckConfirm(confirmMsg, doRemoveTeam);
        } else {
            doRemoveTeam();
        }
    });

}());