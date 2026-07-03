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
    // Deactivate all panes
    document.querySelectorAll('.ck-event-tab-pane').forEach(function (pane) {
        pane.classList.remove('ck-event-tab-pane--active');
    });
    // Deactivate all tab buttons
    document.querySelectorAll('.ck-event-tab').forEach(function (b) {
        b.classList.remove('ck-event-tab--active');
    });
    // Deactivate all header action buttons
    document.querySelectorAll('.ck-event-tab-action').forEach(function (a) {
        a.classList.remove('ck-event-tab-action--active');
    });

    // Activate target pane, tab button and (optionally) header action button
    var pane   = document.getElementById('ckEvtPane-' + tabId);
    var action = document.getElementById('ckEvtAction-' + tabId);
    if (pane)   { pane.classList.add('ck-event-tab-pane--active'); }
    if (btn)    { btn.classList.add('ck-event-tab--active'); }
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
            badge.classList.remove('ck-badge--green', 'ck-badge--orange', 'ck-badge--gray');
            if (done === total) {
                badge.classList.add('ck-badge--green');
            } else if (done > 0) {
                badge.classList.add('ck-badge--orange');
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

    // ── Populate member assign selects (Aufgaben-Tab inline assignment) ──────────
    // Reads from CK_EventDetail.members — no extra Blade variable needed.

    document.querySelectorAll('.ck-task-assign-select').forEach(function (sel) {
        Object.keys(cfg.members).forEach(function (id) {
            var opt       = document.createElement('option');
            opt.value     = id;
            opt.textContent = cfg.members[id].name;
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

    // ── Add Slot (Einsatzplan-Tab) ────────────────────────────────────────────

    var addSlotBtn  = document.getElementById('addSlotBtn');
    var slotTaskSel = document.getElementById('slotTaskSelect');
    var slotMemSel  = document.getElementById('slotMemberSelect');
    var slotFrom    = document.getElementById('slotTimeFrom');
    var slotTo      = document.getElementById('slotTimeTo');

    if (addSlotBtn) {
        addSlotBtn.addEventListener('click', function () {
            var taskId   = slotTaskSel  ? slotTaskSel.value  : '';
            var memberId = slotMemSel   ? slotMemSel.value   : '';
            var timeFrom = slotFrom     ? slotFrom.value     : '';
            var timeTo   = slotTo       ? slotTo.value       : '';

            if (!taskId || !memberId || !timeFrom || !timeTo) {
                [slotTaskSel, slotMemSel, slotFrom, slotTo].forEach(function (el) {
                    if (el && !el.value) { el.classList.add('ck-input--error'); }
                });
                return;
            }
            [slotTaskSel, slotMemSel, slotFrom, slotTo].forEach(function (el) {
                if (el) { el.classList.remove('ck-input--error'); }
            });
            addSlotBtn.disabled = true;

            fetch(cfg.routes.slotsBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
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
                    alert(data.message || 'Fehler beim Speichern.');
                    addSlotBtn.disabled = false;
                }
            })
            .catch(function () {
                alert('Netzwerkfehler. Bitte Seite neu laden.');
                addSlotBtn.disabled = false;
            });
        });
    }

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

}());