/**
 * ClubKit – Event Detail Page
 *
 * Handles:
 *   - Tab switching (ckEvtTab — global, outside IIFE)
 *   - Task completion toggle (AJAX PATCH, optimistic UI, no page reload)
 *   - Task create (AJAX POST, single-step to EventTaskController)
 *   - Task import from global library (AJAX POST with template_id)
 *   - Task remove (AJAX DELETE, page reload after success)
 *   - Task drag & drop reordering via SortableJS (PATCH move)
 *   - Category create / rename / delete (AJAX)
 *   - Member assign via dual-listbox modal (AJAX batch POST/DELETE)
 *   - Slot add/remove (AJAX)
 *   - Function add/remove/assign (AJAX)
 *   - Progress badge + progress bar update after checkbox change
 *
 * Rules:
 *   - No el.style.property assignments → use classList only
 *   - CSS custom properties set via setProperty() (only permitted el.style.* usage)
 *   - All fetch() calls use window.CK_EventDetail for config (data bridge in show.blade.php)
 */

import Sortable from 'sortablejs';

/**
 * Switches the Zeitplan view between Wochenplan and Nach-Kategorie.
 * Global (outside IIFE) so onclick="ckZeitplanView(...)" in Blade can reach it.
 *
 * KW nav visibility is controlled by CSS via [data-view] on #ckZeitplanToolbar:
 *   #ckZeitplanToolbar[data-view="cat"] .ck-kw-nav { display: none !important; }
 * This function updates that attribute AND toggles the week/cat content panels.
 *
 * @param {string}      view  'week' | 'cat'
 * @param {HTMLElement} btn   The clicked toggle button
 */
window.ckZeitplanView = function (view, btn) {
    // Toggle active state on the button strip
    document.querySelectorAll('.ck-zeitplan-toggle__btn').forEach(function (b) {
        b.classList.remove('ck-zeitplan-toggle__btn--active');
    });
    btn.classList.add('ck-zeitplan-toggle__btn--active');

    // Drive KW nav visibility via data attribute (CSS handles display)
    var toolbar = document.getElementById('ckZeitplanToolbar');
    if (toolbar) { toolbar.dataset.view = view; }

    // Toggle the week / category content panels
    var weekEl = document.getElementById('ckZeitplanWeek');
    var catEl  = document.getElementById('ckZeitplanCat');

    if (view === 'week') {
        if (weekEl) { weekEl.classList.remove('is-hidden'); }
        if (catEl)  { catEl.classList.add('is-hidden'); }
    } else {
        if (weekEl) { weekEl.classList.add('is-hidden'); }
        if (catEl)  { catEl.classList.remove('is-hidden'); }
    }
};

/**
 * KW navigation state — mutated by ckKwNav().
 * Initialised from the DOM after the page loads (inside the IIFE).
 */
var CK_KwState = { idx: 0, max: 0 };

/**
 * Navigates the Wochenplan forward or backward by one KW.
 * Global (outside IIFE) so onclick="ckKwNav(...)" in Blade can reach it.
 *
 * @param {number} dir  -1 (previous) | +1 (next)
 */
window.ckKwNav = function (dir) {
    var newIdx = CK_KwState.idx + dir;
    if (newIdx < 0 || newIdx > CK_KwState.max) { return; }

    // Hide the currently active pane
    var current = document.getElementById('ckKwPane-' + CK_KwState.idx);
    if (current) { current.classList.remove('ck-kw-pane--active'); }

    CK_KwState.idx = newIdx;

    // Show the new pane
    var next = document.getElementById('ckKwPane-' + newIdx);
    if (next) {
        next.classList.add('ck-kw-pane--active');
        var labelEl = document.getElementById('ckKwNavLabel');
        var rangeEl = document.getElementById('ckKwNavRange');
        if (labelEl) { labelEl.textContent = next.dataset.kwLabel || ''; }
        if (rangeEl) { rangeEl.textContent = next.dataset.kwRange || ''; }
    }

    // Update button disabled states
    var prevBtn = document.getElementById('ckKwPrev');
    var nextBtn = document.getElementById('ckKwNext');
    if (prevBtn) { prevBtn.disabled = newIdx === 0; }
    if (nextBtn) { nextBtn.disabled = newIdx >= CK_KwState.max; }
};

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

    // Re-initialise SortableJS on any .ck-task-sortable tbody that has not yet been
    // set up (possible if the tasks tab was hidden when events-detail.js first ran).
    if (pane) {
        pane.querySelectorAll('.ck-task-sortable:not([data-sortable-init])').forEach(function (tbody) {
            tbody.setAttribute('data-sortable-init', '1');
            if (typeof Sortable !== 'undefined') {
                Sortable.create(tbody, window._ckSortableOptions || {});
            }
        });
    }
};

/**
 * Open the new-task modal in CREATE mode, pre-selecting a category.
 * Uses direct onclick (not document delegation) because the section header
 * actions div has onclick="event.stopPropagation()" which blocks bubbling.
 *
 * @param {string} catId - Category id to pre-select, or '' for General.
 */
window.ckOpenNewTask = function (catId) {
    // Store the target section so the submit handler can pass it to the server.
    window._ckNewTaskCatId = catId || '';

    // Populate the source dropdown with the Management task library.
    // The first option is always "Neue Aufgabe erstellen" (value="new").
    var srcSel = document.getElementById('newTaskSource');
    if (srcSel) {
        srcSel.innerHTML = '';
        var newOpt = document.createElement('option');
        newOpt.value       = 'new';
        newOpt.textContent = (cfg.i18n && cfg.i18n.sourceNew) ? cfg.i18n.sourceNew : 'Neue Aufgabe erstellen';
        srcSel.appendChild(newOpt);

        var tasks = (window.CK_EventDetail && window.CK_EventDetail.globalTasks) || [];
        tasks.forEach(function (t) {
            var opt        = document.createElement('option');
            opt.value      = t.id;
            opt.textContent = t.name;
            srcSel.appendChild(opt);
        });
    }

    // Reset modal fields to create-mode defaults.
    ckNewTaskToggleSource('new');
    ckModalOpen('newTaskModal');
};

/**
 * Show or hide the name/priority fields depending on the selected source.
 * Called on modal open and on source dropdown change.
 *
 * @param {string} sourceValue - "new" or a ManagementTask id string.
 */
function ckNewTaskToggleSource(sourceValue) {
    var isNew   = (sourceValue === 'new');
    var nameGrp = document.getElementById('newTaskNameGroup');
    var prioGrp = document.getElementById('newTaskPriorityGroup');
    if (nameGrp) { nameGrp.classList.toggle('is-hidden', ! isNew); }
    if (prioGrp) { prioGrp.classList.toggle('is-hidden', ! isNew); }
    // Clear name field when switching to template so stale text does not confuse validation
    if (! isNew) {
        var nameInput = document.getElementById('newTaskName');
        if (nameInput) { nameInput.value = ''; nameInput.classList.remove('ck-input--error'); }
    }
}

/**
 * Open the rename-category modal pre-filled with the given category data.
 * Uses direct onclick for the same stopPropagation reason as ckOpenNewTask.
 *
 * @param {string} catId   - Category id.
 * @param {string} catName - Current category name.
 */
window.ckOpenCatRename = function (catId, catName, catColor) {
    window._ckRenameCatId = catId;
    var nameInput = document.getElementById('renameCatName');
    if (nameInput) { nameInput.value = catName || ''; }
    // Pre-select the current color in the color picker
    var picker = document.getElementById('renameCatColorPicker');
    if (picker) {
        var radios = picker.querySelectorAll('input[type=radio]');
        radios.forEach(function (r) {
            r.checked = (r.value === (catColor || ''));
            r.closest('.ck-color-swatch').classList.toggle('ck-color-swatch--selected', r.checked);
        });
    }
    ckModalOpen('renameCatModal');
};

/**
 * Client-side column sort for all .ck-task-sortable tbodies on the page.
 * No page reload — manipulates DOM rows directly within each section independently.
 * Sort state is toggled: first click ASC, second click DESC, same order persists across sections.
 *
 * @param {string}      column - 'name' | 'priority' | 'deadline'
 * @param {HTMLElement} btn    - The clicked sort button (for visual state update).
 */
/**
 * Per-section client-side column sort.
 * Only the section that owns the clicked button is sorted and updated visually.
 * Other sections are unaffected.
 *
 * @param {string}      column - 'name' | 'priority' | 'deadline'
 * @param {HTMLElement} btn    - The clicked sort <button> inside a section thead.
 */
window.ckTaskSortBy = function (column, btn) {
    // Find the tbody belonging to the same section as the clicked header.
    // Use native Element.closest() — no IIFE-scoped helper needed.
    var thead  = btn.closest('thead');
    var table  = thead ? thead.parentElement : null;
    var tbody  = table ? table.querySelector('.ck-task-sortable') : null;
    if (! tbody) { return; }

    // Toggle direction within this section: track per-thead state via data attribute.
    var prevCol = thead.dataset.sortCol  || '';
    var prevDir = thead.dataset.sortDir  || 'asc';
    var newDir  = (prevCol === column && prevDir === 'asc') ? 'desc' : 'asc';
    thead.dataset.sortCol = column;
    thead.dataset.sortDir = newDir;

    // Update visual state — only this section's header buttons.
    thead.querySelectorAll('.ck-task-sort-btn').forEach(function (b) {
        b.classList.remove('ck-sort-link--active');
        b.querySelector('.ck-sort-icon').textContent = '⇅';
    });
    btn.classList.add('ck-sort-link--active');
    btn.querySelector('.ck-sort-icon').textContent = newDir === 'asc' ? '↑' : '↓';

    // Build sort key from dataset attribute name: 'name' → 'sortName', etc.
    var dataKey = 'sort' + column.charAt(0).toUpperCase() + column.slice(1);

    var realRows = Array.prototype.slice.call(
        tbody.querySelectorAll('.ck-task-row:not(.ck-task-row--empty)')
    );

    realRows.sort(function (a, b) {
        var aVal = a.dataset[dataKey] || '';
        var bVal = b.dataset[dataKey] || '';
        if (aVal < bVal) { return newDir === 'asc' ? -1 :  1; }
        if (aVal > bVal) { return newDir === 'asc' ?  1 : -1; }
        return 0;
    });

    realRows.forEach(function (row) { tbody.appendChild(row); });
};

(function () {
    'use strict';

    var cfg  = window.CK_EventDetail;
    if (! cfg) { return; }

    var csrf = cfg.csrf;

    // ── Restore active tab after AJAX-triggered page reload ───────────────────

    var _restoredTab = sessionStorage.getItem('ck_evt_active_tab');
    if (_restoredTab) {
        sessionStorage.removeItem('ck_evt_active_tab');
        var _restoredBtn = document.querySelector('.ck-local-tab[onclick*="\'' + _restoredTab + '\'"]');
        if (_restoredBtn) { ckEvtTab(_restoredTab, _restoredBtn); }
    }

    // ── Wochenplan KW navigation: initialise state from DOM ──────────────────

    (function () {
        var weekContainer = document.getElementById('ckZeitplanWeek');
        if (! weekContainer) { return; }

        var allPanes = weekContainer.querySelectorAll('.ck-kw-pane');
        CK_KwState.max = Math.max(0, allPanes.length - 1);
        CK_KwState.idx = parseInt(weekContainer.dataset.activeIdx || '0', 10);

        var prevBtn = document.getElementById('ckKwPrev');
        var nextBtn = document.getElementById('ckKwNext');
        if (prevBtn) { prevBtn.disabled = CK_KwState.idx === 0; }
        if (nextBtn) { nextBtn.disabled = CK_KwState.idx >= CK_KwState.max; }
    }());

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Walks up the DOM tree and returns the first ancestor (or self) matching the selector.
     *
     * @param  {Element|null} el
     * @param  {string}       selector
     * @return {Element|null}
     */
    function closest(el, selector) {
        while (el && ! el.matches(selector)) {
            el = el.parentElement;
        }
        return el || null;
    }

    /**
     * Persists the active tab ID to sessionStorage and reloads the page.
     * The IIFE reads this value on next load and re-activates the correct tab.
     */
    function reloadKeepingTab() {
        var activePane = document.querySelector('.ck-local-section.ck-local-section--active');
        if (activePane) {
            sessionStorage.setItem('ck_evt_active_tab', activePane.id.replace('ckEvtPane-', ''));
        }
        window.location.reload();
    }

    /**
     * Updates the section badge text and colour class after a completion toggle.
     *
     * @param {string} sectionSlug - The value of the data-section attribute on the section container.
     */
    function updateSectionBadge(sectionSlug) {
        var section = document.querySelector('[data-section="' + sectionSlug + '"]');
        if (! section) { return; }

        var done  = section.querySelectorAll('.ck-task-row--done').length;
        var total = section.querySelectorAll('.ck-task-row').length;

        var badge = document.querySelector('[data-section-badge="' + sectionSlug + '"]');
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
     * Updates the global progress bar and completed-task counter.
     * Uses setProperty() to write the --progress CSS custom property —
     * the only permitted el.style.* usage (CSS variable, not a presentation property).
     */
    function updateGlobalProgress() {
        var allRows  = document.querySelectorAll('.ck-task-row');
        var doneRows = document.querySelectorAll('.ck-task-row--done');
        var total    = allRows.length;
        var done     = doneRows.length;

        var counter = document.getElementById('global-done-count');
        if (counter) { counter.textContent = String(done); }

        var bar = document.querySelector('.ck-event-progress__fill');
        if (bar && total > 0) {
            bar.style.setProperty('--progress', Math.round(done / total * 100) + '%');
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

    // ── Import task from global library (Tab 2: "Aus Bibliothek importieren") ──
    // importTaskSelect carries the global ManagementTask id as template_id.
    // EventTaskController.store() copies name + priority from the template.

    var importTaskBtn    = document.getElementById('importTaskBtn');
    var importTaskSelect = document.getElementById('importTaskSelect');

    if (importTaskBtn && importTaskSelect) {
        importTaskBtn.addEventListener('click', function () {
            var templateId = importTaskSelect.value;
            if (! templateId) {
                importTaskSelect.classList.add('ck-input--error');
                return;
            }
            importTaskSelect.classList.remove('ck-input--error');
            importTaskBtn.disabled = true;

            fetch(cfg.routes.tasksBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ template_id: parseInt(templateId, 10) }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    reloadKeepingTab();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Importieren der Aufgabe.');
                    importTaskBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                importTaskBtn.disabled = false;
            });
        });
    }

    // ── Task modal mode tracking ─────────────────────────────────────────────
    // null = create mode (POST to tasksBase), string = edit mode (PATCH to tasksBase/id)

    var _taskEditId         = null;
    var _taskModalOrigTitle = null;

    (function () {
        var modal = document.getElementById('newTaskModal');
        if (! modal) { return; }
        var titleEl = modal.querySelector('.ck-modal__title');
        if (titleEl) { _taskModalOrigTitle = titleEl.textContent; }

        // Reset to create mode whenever the modal closes.
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.attributeName !== 'class') { return; }
                if (modal.classList.contains('ck-modal--open')) { return; }
                _taskEditId = null;
                var srcGroup = document.getElementById('newTaskSourceGroup');
                if (srcGroup) { srcGroup.classList.remove('is-hidden'); }
                var t = modal.querySelector('.ck-modal__title');
                if (t && _taskModalOrigTitle) { t.textContent = _taskModalOrigTitle; }
                var nameInput = document.getElementById('newTaskName');
                if (nameInput) { nameInput.value = ''; }
                var deadlineInput = document.getElementById('newTaskDeadline');
                if (deadlineInput) { deadlineInput.value = ''; }
            });
        }).observe(modal, { attributes: true });
    }());

    // ── Section "+" button: open newTaskModal in CREATE mode ─────────────────
    // stopPropagation prevents the <details> element from toggling.

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-event-section__add-task-btn');
        if (! btn) { return; }

        e.stopPropagation();
        e.preventDefault();

        _taskEditId = null;
        var modal = document.getElementById('newTaskModal');
        if (modal) {
            var t = modal.querySelector('.ck-modal__title');
            if (t && _taskModalOrigTitle) { t.textContent = _taskModalOrigTitle; }
        }

        var catSel = document.getElementById('newTaskCategoryId');
        if (catSel) { catSel.value = btn.dataset.defaultCatId || ''; }

        ckModalOpen('newTaskModal');
    });

    // ── Edit task: pre-fill newTaskModal in EDIT mode ────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-edit-btn');
        if (! btn) { return; }

        _taskEditId = btn.dataset.taskId;

        var nameInput     = document.getElementById('newTaskName');
        var catSelect     = document.getElementById('newTaskCategoryId');
        var prioSelect    = document.getElementById('newTaskPriority');
        var deadlineInput = document.getElementById('newTaskDeadline');

        if (nameInput)     { nameInput.value     = btn.dataset.taskName     || ''; }
        if (prioSelect)    { prioSelect.value    = btn.dataset.taskPriority || 'normal'; }
        if (deadlineInput) { deadlineInput.value = btn.dataset.taskDeadline || ''; }
        if (catSelect)     { catSelect.value     = btn.dataset.taskCatId    || ''; }

        // Edit mode: hide source dropdown (task already exists),
        // always show name and priority fields.
        var srcGroup  = document.getElementById('newTaskSourceGroup');
        var nameGroup = document.getElementById('newTaskNameGroup');
        var prioGroup = document.getElementById('newTaskPriorityGroup');
        if (srcGroup)  { srcGroup.classList.add('is-hidden'); }
        if (nameGroup) { nameGroup.classList.remove('is-hidden'); }
        if (prioGroup) { prioGroup.classList.remove('is-hidden'); }

        var modal = document.getElementById('newTaskModal');
        if (modal) {
            var t = modal.querySelector('.ck-modal__title');
            if (t) { t.textContent = 'Aufgabe bearbeiten'; }
        }

        ckModalOpen('newTaskModal');
    });

    // ── Remove Task ───────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-remove-btn');
        if (! btn) { return; }

        var taskId = btn.dataset.taskId;
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
                reloadKeepingTab();
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
                reloadKeepingTab();
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

    // ── Category progress bars (overview tab) ──────────────────────────────────
    // CSS custom property --progress: only permitted el.style.* usage.

    document.querySelectorAll('.ck-cat-progress__fill').forEach(function (fill) {
        fill.style.setProperty('--progress', (fill.dataset.progress || '0') + '%');
    });

    // ── New Task Modal submit (Tab 2 → "Neue Aufgabe") ────────────────────────
    // Single-step: POST directly to tasksBase (EventTaskController).
    // EventTask is fully self-contained — no separate ManagementTask creation needed.

    // ── New-task source dropdown: show/hide name + priority fields ─────────
    var newTaskSrcSel = document.getElementById('newTaskSource');
    if (newTaskSrcSel) {
        newTaskSrcSel.addEventListener('change', function () {
            ckNewTaskToggleSource(this.value);
        });
    }

    var newTaskBtn = document.getElementById('newTaskSubmitBtn');

    if (newTaskBtn) {
        newTaskBtn.addEventListener('click', function () {
            var srcSel        = document.getElementById('newTaskSource');
            var nameInput     = document.getElementById('newTaskName');
            var prioSelect    = document.getElementById('newTaskPriority');
            var deadlineInput = document.getElementById('newTaskDeadline');
            var memberSelect  = document.getElementById('newTaskMemberId');

            var sourceVal  = srcSel ? srcSel.value : 'new';
            var isNew      = (sourceVal === 'new');

            // Build request body based on source selection.
            var body = {};

            if (isNew) {
                // Creating a new task: name + priority are required.
                var name = nameInput ? nameInput.value.trim() : '';
                if (! name) {
                    if (nameInput) { nameInput.classList.add('ck-input--error'); }
                    return;
                }
                if (nameInput) { nameInput.classList.remove('ck-input--error'); }
                body.name     = name;
                body.priority = (prioSelect && prioSelect.value) ? prioSelect.value : 'normal';
            } else {
                // Importing from Management task library: pass the template id.
                body.template_id = parseInt(sourceVal, 10);
            }

            // Category: determined by which section "+" was clicked (stored in window._ckNewTaskCatId).
            var catId = window._ckNewTaskCatId || '';
            if (catId !== '' && catId !== 'allgemein') {
                body.category_id = parseInt(catId, 10);
            }

            if (deadlineInput && deadlineInput.value) { body.deadline_at = deadlineInput.value; }

            newTaskBtn.disabled = true;

            // Edit mode (name/priority update): PATCH; create mode: POST.
            var taskUrl    = _taskEditId ? (cfg.routes.tasksBase + '/' + _taskEditId) : cfg.routes.tasksBase;
            var taskMethod = _taskEditId ? 'PATCH' : 'POST';

            fetch(taskUrl, {
                method:  taskMethod,
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify(body),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (! data.success) {
                    ckNotify('error', data.message || 'Fehler beim Speichern der Aufgabe.');
                    newTaskBtn.disabled = false;
                    return;
                }

                _taskEditId = null; // Reset after successful save.

                // Optional: assign the selected member immediately (event_task_id required!)
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
                            event_task_id: data.task.id,
                            member_id:     parseInt(memberSelect.value, 10),
                        }),
                    })
                    .then(function () { reloadKeepingTab(); })
                    .catch(function ()  { reloadKeepingTab(); });
                } else {
                    reloadKeepingTab();
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newTaskBtn.disabled = false;
            });
        });
    }

    // ── New Category Modal submit (Tab 2 → "Neue Kategorie") ─────────────────
    // POSTs to categoriesBase = events/{event}/task-categories (EventTaskCategoryController).
    // Response: { success: true, category: { id, name, color, sort_order } }

    var newCatBtn = document.getElementById('newCatSubmitBtn');

    if (newCatBtn) {
        newCatBtn.addEventListener('click', function () {
            var nameInput = document.getElementById('newCatName');
            var name      = nameInput ? nameInput.value.trim() : '';
            var colorInput = document.querySelector('#newCatColorPicker input[type=radio]:checked');
            var color = colorInput ? colorInput.value : '';
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
                body: JSON.stringify({ name: name, color: color }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    ckModalClose(null, 'newCatModal');
                    reloadKeepingTab();
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

    // ── Rename Category ───────────────────────────────────────────────────────

    // Module-level state: which category is currently being renamed.
    // Stored on window so ckOpenCatRename() (defined outside IIFE) can share the same slot.

    // Keep supporting the document-level delegation for any other rename triggers.
    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-cat-rename-btn');
        if (! btn) { return; }
        window.ckOpenCatRename(btn.dataset.catId, btn.dataset.catName);
    });

    // Submit rename: PATCH categoriesBase/{id} { name }
    var renameCatBtn = document.getElementById('renameCatSubmitBtn');

    if (renameCatBtn) {
        renameCatBtn.addEventListener('click', function () {
            if (! window._ckRenameCatId) { return; }

            var nameInput   = document.getElementById('renameCatName');
            var colorInput  = document.querySelector('#renameCatColorPicker input[type=radio]:checked');
            var name        = nameInput ? nameInput.value.trim() : '';
            var color       = colorInput ? colorInput.value : '';
            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            renameCatBtn.disabled = true;

            fetch(cfg.routes.categoriesBase + '/' + window._ckRenameCatId, {
                method:  'PATCH',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ name: name, color: color }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    ckModalClose(null, 'renameCatModal');
                    reloadKeepingTab();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Umbenennen der Kategorie.');
                    renameCatBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                renameCatBtn.disabled = false;
            });
        });
    }

    // ── Delete Category ───────────────────────────────────────────────────────
    // DELETE categoriesBase/{id}. DB SET NULL on event_tasks.category_id moves
    // orphaned tasks to the "Allgemein" section automatically (no tasks deleted).

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-cat-delete-btn');
        if (! btn) { return; }

        var catId     = btn.dataset.catId;
        var catName   = btn.dataset.catName;
        var taskCount = parseInt(btn.dataset.taskCount || '0', 10);
        if (! catId) { return; }

        var msg = taskCount > 0
            ? 'Kategorie „' + catName + '" löschen? ' + taskCount + ' Aufgabe(n) werden nach „Allgemein" verschoben.'
            : 'Kategorie „' + catName + '" wirklich löschen?';

        window.ckConfirm(msg, function () {
            fetch(cfg.routes.categoriesBase + '/' + catId, {
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
                    reloadKeepingTab();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Löschen der Kategorie.');
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
            });
        });
    });

    // ── Assign Member to Task (dual-listbox modal) ────────────────────────────
    // .ck-task-assign-btn click → populate taskAssignModal → user moves members
    //   between Available (left) and Assigned (right) selects.
    // taskAssignSaveBtn → diff original vs new → batch POST/DELETE.

    // Module-level state for the currently open taskAssignModal
    var _taskAssignTaskId   = null;
    // key = memberId (string), value = { etmId: string, memberId: string, name: string }
    var _taskAssignOriginal = {};

    /**
     * Creates an <option> element for the dual-listbox selects.
     *
     * @param  {string} value - Option value (member ID as string).
     * @param  {string} label - Display text.
     * @return {HTMLOptionElement}
     */
    function _assignOpt(value, label) {
        var opt         = document.createElement('option');
        opt.value       = value;
        opt.textContent = label;
        return opt;
    }

    // Open modal: read current assignments from DOM, populate both listbox panels
    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-assign-btn');
        if (! btn) { return; }

        _taskAssignTaskId   = btn.dataset.taskId;
        _taskAssignOriginal = {};

        // Collect current assignments from the task row DOM.
        // .ck-etm-remove-btn carries data-member-id + data-member-name (added in Step 7).
        var row = document.querySelector('.ck-task-row[data-task-id="' + _taskAssignTaskId + '"]');
        if (row) {
            row.querySelectorAll('.ck-etm-remove-btn').forEach(function (removeBtn) {
                var etmId    = removeBtn.dataset.etmId;
                var memberId = removeBtn.dataset.memberId;
                var name     = removeBtn.dataset.memberName || cfg.members[memberId]?.name || '';
                if (memberId) {
                    _taskAssignOriginal[memberId] = { etmId: etmId, memberId: memberId, name: name };
                }
            });
        }

        // Set the modal subtitle to the task name
        var label = document.getElementById('taskAssignLabel');
        if (label) { label.textContent = btn.dataset.taskName || ''; }

        // Populate Available (left) and Assigned (right) selects
        var availSel    = document.getElementById('taskAssignAvailableList');
        var assignedSel = document.getElementById('taskAssignAssignedList');
        if (! availSel || ! assignedSel) { return; }

        availSel.innerHTML    = '';
        assignedSel.innerHTML = '';

        Object.keys(cfg.members).forEach(function (memberId) {
            var member = cfg.members[memberId];
            if (_taskAssignOriginal[memberId]) {
                assignedSel.appendChild(_assignOpt(memberId, member.name));
            } else {
                availSel.appendChild(_assignOpt(memberId, member.name));
            }
        });

        ckModalOpen('taskAssignModal');
    });

    // → button: move selected options from Available to Assigned
    var taskAssignAddBtn = document.getElementById('taskAssignAddBtn');
    if (taskAssignAddBtn) {
        taskAssignAddBtn.addEventListener('click', function () {
            var availSel    = document.getElementById('taskAssignAvailableList');
            var assignedSel = document.getElementById('taskAssignAssignedList');
            if (! availSel || ! assignedSel) { return; }
            Array.from(availSel.selectedOptions).forEach(function (opt) {
                assignedSel.appendChild(opt);
            });
        });
    }

    // ← button: move selected options from Assigned to Available
    var taskAssignRemoveBtn = document.getElementById('taskAssignRemoveBtn');
    if (taskAssignRemoveBtn) {
        taskAssignRemoveBtn.addEventListener('click', function () {
            var availSel    = document.getElementById('taskAssignAvailableList');
            var assignedSel = document.getElementById('taskAssignAssignedList');
            if (! availSel || ! assignedSel) { return; }
            Array.from(assignedSel.selectedOptions).forEach(function (opt) {
                availSel.appendChild(opt);
            });
        });
    }

    // Save: diff original vs current listbox state → batch AJAX add/remove
    var taskAssignSaveBtn = document.getElementById('taskAssignSaveBtn');
    if (taskAssignSaveBtn) {
        taskAssignSaveBtn.addEventListener('click', function () {
            if (! _taskAssignTaskId) { return; }

            var assignedSel = document.getElementById('taskAssignAssignedList');
            if (! assignedSel) { return; }

            // Build current "assigned" set from the right listbox
            var nowAssigned = {};
            Array.from(assignedSel.options).forEach(function (opt) {
                nowAssigned[opt.value] = true;
            });

            var toAdd    = [];  // member IDs to add via POST /members
            var toRemove = [];  // ETM IDs to remove via DELETE /members/{id}

            Object.keys(nowAssigned).forEach(function (memberId) {
                if (! _taskAssignOriginal[memberId]) { toAdd.push(memberId); }
            });

            Object.keys(_taskAssignOriginal).forEach(function (memberId) {
                if (! nowAssigned[memberId]) { toRemove.push(_taskAssignOriginal[memberId].etmId); }
            });

            // No changes → just close
            if (toAdd.length === 0 && toRemove.length === 0) {
                ckModalClose(null, 'taskAssignModal');
                return;
            }

            taskAssignSaveBtn.disabled = true;

            var addPromises = toAdd.map(function (memberId) {
                return fetch(cfg.routes.membersBase, {
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
                });
            });

            var removePromises = toRemove.map(function (etmId) {
                return fetch(cfg.routes.membersBase + '/' + etmId, {
                    method:  'DELETE',
                    headers: {
                        'X-CSRF-TOKEN':     csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            });

            Promise.all(addPromises.concat(removePromises))
                .then(function () {
                    ckModalClose(null, 'taskAssignModal');
                    reloadKeepingTab();
                })
                .catch(function () {
                    ckNotify('error', 'Netzwerkfehler beim Speichern der Zuweisung.');
                    taskAssignSaveBtn.disabled = false;
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
                    reloadKeepingTab();
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

    // ── Add function to event (functions tab: "add function" button) ────────────

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
                    reloadKeepingTab();
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

    // ── Remove function from event (functions tab: × button) ───────────────────

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
                    reloadKeepingTab();
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
                reloadKeepingTab();
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
            if (data.success) { reloadKeepingTab(); }
            else              { btn.disabled = false; }
        })
        .catch(function () { btn.disabled = false; });
    });

    // ── Add team to event (teams tab: "add team" button) ───────────────────────

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
                    reloadKeepingTab();
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

    // ── Remove team from event (teams tab: × button) ────────────────────────────

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
                    reloadKeepingTab();
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

    // ── SortableJS: drag & drop task reordering within and across categories ──
    // Initialised on every .ck-task-sortable tbody on the page.
    // Shared group 'event-tasks' allows dragging between category sections.
    // On drop: PATCH tasksBase/{taskId}/move { category_id, sort_order }.

    document.querySelectorAll('.ck-task-sortable').forEach(function (tbody) {
        tbody.setAttribute('data-sortable-init', '1');
        Sortable.create(tbody, {
            group:       'event-tasks',                    // shared group → cross-section drag enabled
            handle:      'td:not(.ck-table__col--actions)', // all cells except action buttons are the drag target
            animation:   150,
            ghostClass:  'sortable-ghost',
            chosenClass: 'sortable-chosen',

            onEnd: function (evt) {
                var taskRow     = evt.item;
                var taskId      = taskRow.dataset.taskId;
                var fromTbody   = evt.from;
                var toTbody     = evt.to;
                var rawCatId    = toTbody.dataset.catId;

                // 'allgemein' and '' both map to category_id = null (uncategorised section)
                var catId = (rawCatId === 'allgemein' || rawCatId === '')
                    ? null
                    : parseInt(rawCatId, 10);

                if (! taskId) { return; }

                // ── Update empty-state rows immediately (DOM-only, no reload) ──
                // Remove empty-state from destination (a real row just arrived).
                var toEmpty = toTbody.querySelector('.ck-task-row--empty');
                if (toEmpty) { toEmpty.remove(); }

                // Add empty-state to source if it now contains no real task rows.
                if (fromTbody !== toTbody) {
                    var realRows = fromTbody.querySelectorAll('.ck-task-row:not(.ck-task-row--empty)');
                    if (realRows.length === 0) {
                        var emptyRow = document.createElement('tr');
                        emptyRow.className = 'ck-task-row--empty';
                        emptyRow.innerHTML = '<td colspan="8" class="ck-empty-state">'
                            + (window.CK_EventDetail.i18n && window.CK_EventDetail.i18n.sectionEmpty
                                ? window.CK_EventDetail.i18n.sectionEmpty
                                : 'Noch keine Aufgaben in diesem Bereich.')
                            + '</td>';
                        fromTbody.appendChild(emptyRow);
                    }

                    // ── Update section count badges ──
                    updateSectionBadge(fromTbody);
                    updateSectionBadge(toTbody);
                }

                fetch(cfg.routes.tasksBase + '/' + taskId + '/move', {
                    method:  'PATCH',
                    headers: {
                        'Content-Type':     'application/json',
                        'X-CSRF-TOKEN':     csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept':           'application/json',
                    },
                    body: JSON.stringify({
                        category_id: catId,
                        sort_order:  evt.newIndex,
                    }),
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (! data.success) {
                        ckNotify('error', 'Fehler beim Verschieben der Aufgabe.');
                    }
                    // DOM position already updated by SortableJS; no reload needed.
                })
                .catch(function () {
                    ckNotify('error', 'Netzwerkfehler beim Verschieben. Seite neu laden.');
                });
            },
        });
    });

}());