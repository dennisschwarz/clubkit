/**
 * Global functions that must be accessible from Blade onclick="" attributes.
 * Exported as a single init function; the main entry point registers them on window.
 *
 * @param {object} Sortable - SortableJS constructor (passed from main entry point)
 */
export function initGlobals(Sortable) {
    // ── KW navigation state ───────────────────────────────────────────────────
    var CK_KwState = { idx: 0, max: 0 };

    /**
     * Switches the Zeitplan view between Wochenplan and Nach-Kategorie.
     *
     * @param {string}      view  'week' | 'cat'
     * @param {HTMLElement} btn   The clicked toggle button
     */
    window.ckZeitplanView = function (view, btn) {
        document.querySelectorAll('.ck-zeitplan-toggle__btn').forEach(function (b) {
            b.classList.remove('ck-zeitplan-toggle__btn--active');
        });
        btn.classList.add('ck-zeitplan-toggle__btn--active');

        var toolbar = document.getElementById('ckZeitplanToolbar');
        if (toolbar) { toolbar.dataset.view = view; }

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
     * Navigates the Wochenplan forward or backward by one KW.
     *
     * @param {number} dir  -1 (previous) | +1 (next)
     */
    window.ckKwNav = function (dir) {
        var newIdx = CK_KwState.idx + dir;
        if (newIdx < 0 || newIdx > CK_KwState.max) { return; }

        var current = document.getElementById('ckKwPane-' + CK_KwState.idx);
        if (current) { current.classList.remove('ck-kw-pane--active'); }

        CK_KwState.idx = newIdx;

        var next = document.getElementById('ckKwPane-' + newIdx);
        if (next) {
            next.classList.add('ck-kw-pane--active');
            var labelEl = document.getElementById('ckKwNavLabel');
            var rangeEl = document.getElementById('ckKwNavRange');
            if (labelEl) { labelEl.textContent = next.dataset.kwLabel || ''; }
            if (rangeEl) { rangeEl.textContent = next.dataset.kwRange || ''; }
        }

        var prevBtn = document.getElementById('ckKwPrev');
        var nextBtn = document.getElementById('ckKwNext');
        if (prevBtn) { prevBtn.disabled = newIdx === 0; }
        if (nextBtn) { nextBtn.disabled = newIdx >= CK_KwState.max; }
    };

    /**
     * Initialises KW navigation state from the DOM.
     * Called once on page load from the main IIFE.
     */
    window.ckInitKwState = function () {
        var weekContainer = document.getElementById('ckZeitplanWeek');
        if (! weekContainer) { return; }
        var allPanes = weekContainer.querySelectorAll('.ck-kw-pane');
        CK_KwState.max = Math.max(0, allPanes.length - 1);
        CK_KwState.idx = parseInt(weekContainer.dataset.activeIdx || '0', 10);
        var prevBtn = document.getElementById('ckKwPrev');
        var nextBtn = document.getElementById('ckKwNext');
        if (prevBtn) { prevBtn.disabled = CK_KwState.idx === 0; }
        if (nextBtn) { nextBtn.disabled = CK_KwState.idx >= CK_KwState.max; }
    };

    /**
     * Switches the active event detail tab.
     *
     * @param {string}      tabId  Pane suffix (e.g. 'tasks', 'slots', 'functions')
     * @param {HTMLElement} btn    The clicked tab button
     */
    window.ckEvtTab = function (tabId, btn) {
        document.querySelectorAll('.ck-local-section').forEach(function (pane) {
            pane.classList.remove('ck-local-section--active');
        });
        document.querySelectorAll('.ck-local-tab').forEach(function (b) {
            b.classList.remove('ck-local-tab--active');
        });

        // Toggle header action groups.
        // Single-tab:  id="ckEvtAction-{tabId}"
        // Multi-tab:   data-ck-tab-actions="tasks slots" (space-separated list of tab IDs)
        document.querySelectorAll('.ck-event-tab-action').forEach(function (a) {
            var multi = (a.dataset.ckTabActions || '').split(' ').filter(Boolean);
            var match = multi.length > 0
                ? multi.indexOf(tabId) !== -1
                : a.id === 'ckEvtAction-' + tabId;
            if (match) {
                a.classList.add('ck-event-tab-action--active');
            } else {
                a.classList.remove('ck-event-tab-action--active');
            }
        });

        var pane = document.getElementById('ckEvtPane-' + tabId);
        if (pane) { pane.classList.add('ck-local-section--active'); }
        if (btn)  { btn.classList.add('ck-local-tab--active'); }

        if (pane) {
            pane.querySelectorAll('.ck-task-sortable:not([data-sortable-init])').forEach(function (tbody) {
                tbody.setAttribute('data-sortable-init', '1');
                if (Sortable) {
                    Sortable.create(tbody, window._ckSortableOptions || {});
                }
            });
        }
    };

    /**
     * Open the new-task modal in CREATE mode, pre-selecting a category.
     *
     * @param {string} catId - Category id to pre-select, or '' for General.
     */
    window.ckOpenNewTask = function (catId) {
        window._ckNewTaskCatId = catId || '';

        var srcSel = document.getElementById('newTaskSource');
        if (srcSel) {
            srcSel.innerHTML = '';
            var newOpt = document.createElement('option');
            newOpt.value = 'new';
            var cfg = window.CK_EventDetail;
            newOpt.textContent = (cfg && cfg.i18n && cfg.i18n.sourceNew)
                ? cfg.i18n.sourceNew
                : 'Neue Aufgabe erstellen';
            srcSel.appendChild(newOpt);

            var tasks = (cfg && cfg.globalTasks) || [];
            tasks.forEach(function (t) {
                var opt         = document.createElement('option');
                opt.value       = t.id;
                opt.textContent = t.name;
                srcSel.appendChild(opt);
            });
        }

        ckNewTaskToggleSource('new');
        ckModalOpen('newTaskModal');
    };

    /**
     * Show or hide the name/priority fields depending on the selected source.
     *
     * @param {string} sourceValue - "new" or a ManagementTask id string.
     */
    window.ckNewTaskToggleSource = function (sourceValue) {
        var isNew   = (sourceValue === 'new');
        var nameGrp = document.getElementById('newTaskNameGroup');
        var prioGrp = document.getElementById('newTaskPriorityGroup');
        if (nameGrp) { nameGrp.classList.toggle('is-hidden', ! isNew); }
        if (prioGrp) { prioGrp.classList.toggle('is-hidden', ! isNew); }
        if (! isNew) {
            var nameInput = document.getElementById('newTaskName');
            if (nameInput) { nameInput.value = ''; nameInput.classList.remove('ck-input--error'); }
        }
    };

    // Keep the internal name accessible without window. prefix inside this module.
    var ckNewTaskToggleSource = window.ckNewTaskToggleSource;

    /**
     * Open the rename-category modal pre-filled with the given category data.
     *
     * @param {string} catId   - Category id.
     * @param {string} catName - Current category name.
     * @param {string} catColor - Current color value.
     */
    window.ckOpenCatRename = function (catId, catName, catColor) {
        window._ckRenameCatId = catId;
        var nameInput = document.getElementById('renameCatName');
        if (nameInput) { nameInput.value = catName || ''; }
        var picker = document.getElementById('renameCatColorPicker');
        if (picker) {
            picker.querySelectorAll('input[type=radio]').forEach(function (r) {
                r.checked = (r.value === (catColor || ''));
                r.closest('.ck-color-swatch').classList.toggle('ck-color-swatch--selected', r.checked);
            });
        }
        ckModalOpen('renameCatModal');
    };

    /**
     * Per-section client-side column sort.
     *
     * @param {string}      column - 'name' | 'priority' | 'deadline'
     * @param {HTMLElement} btn    - The clicked sort button.
     */
    window.ckTaskSortBy = function (column, btn) {
        var thead  = btn.closest('thead');
        var table  = thead ? thead.parentElement : null;
        var tbody  = table ? table.querySelector('.ck-task-sortable') : null;
        if (! tbody) { return; }

        var prevCol = thead.dataset.sortCol || '';
        var prevDir = thead.dataset.sortDir || 'asc';
        var newDir  = (prevCol === column && prevDir === 'asc') ? 'desc' : 'asc';
        thead.dataset.sortCol = column;
        thead.dataset.sortDir = newDir;

        thead.querySelectorAll('.ck-task-sort-btn').forEach(function (b) {
            b.classList.remove('ck-sort-link--active');
            b.querySelector('.ck-sort-icon').textContent = '\u21c5';
        });
        btn.classList.add('ck-sort-link--active');
        btn.querySelector('.ck-sort-icon').textContent = newDir === 'asc' ? '\u2191' : '\u2193';

        var dataKey  = 'sort' + column.charAt(0).toUpperCase() + column.slice(1);
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

    // ── Shift plan: open slot config modal ───────────────────────────────────

    /**
     * Opens the shift plan config modal and pre-fills its fields.
     *
     * When taskId is null (called from the action-bar "+" button),
     * no task is pre-selected and all fields are cleared.
     *
     * When taskId is a known task ID, the select is set and the four slot-config
     * fields are pre-filled from window.CK_EventDetail.slotConfig[taskId].
     *
     * @param {number|null} taskId - EventTask id, or null to reset.
     */
    window.ckOpenShiftConfig = function (taskId) {
        var taskSel     = document.getElementById('ckShiftConfigTaskId');
        var startInp    = document.getElementById('ckShiftConfigStart');
        var endInp      = document.getElementById('ckShiftConfigEnd');
        var intervalSel = document.getElementById('ckShiftConfigInterval');
        var capacityInp = document.getElementById('ckShiftConfigCapacity');

        // Clear error states.
        [taskSel, startInp, endInp, intervalSel, capacityInp].forEach(function (el) {
            if (el) { el.classList.remove('ck-input--error'); }
        });

        if (taskId && taskSel) {
            taskSel.value = String(taskId);
        } else if (taskSel) {
            taskSel.selectedIndex = 0;
        }

        // Pre-fill from current slot config when available.
        var cfg        = window.CK_EventDetail || {};
        var slotConfig = (cfg.slotConfig || {})[taskId] || null;

        if (startInp)    { startInp.value    = slotConfig ? (slotConfig.slot_start_time       || '') : ''; }
        if (endInp)      { endInp.value      = slotConfig ? (slotConfig.slot_end_time         || '') : ''; }
        if (intervalSel) { intervalSel.value = slotConfig ? String(slotConfig.slot_interval_minutes || 60) : '60'; }
        if (capacityInp) { capacityInp.value = slotConfig ? String(slotConfig.slot_capacity   || 1)  : '1'; }

        ckModalOpen('ckShiftConfigModal');
    };

    // ── Shift plan: open member assign modal ──────────────────────────────────

    /**
     * Opens the shift-assignment modal showing ALL time slots of a task.
     *
     * Left column:  member pool — members not assigned to ANY slot of this task.
     * Right column: every slot of the task rendered as an independent drop-zone.
     *
     * Called from onclick on any grid cell in the shift-plan tab.
     * All cells in a task row pass the same allSlots array, so clicking any cell
     * opens the full task view rather than a single-slot view.
     *
     * SortableJS init + AJAX (assign / remove) handled in slot-modal.js.
     * SortableJS is re-initialised on every open because zones are rebuilt.
     *
     * @param {number}      taskId  - EventTask id (integer literal in onclick).
     * @param {HTMLElement} element - The clicked <td>; carries data-task-name attribute.
     *
     * Slot data is read from window.CK_ShiftGrid[taskId] (set by event-slots-panel.blade.php
     * via @push('scripts')). This avoids embedding raw JSON inside an onclick="" attribute
     * (which breaks HTML parsing because @js() outputs unescaped double-quotes).
     */
    window.ckOpenShiftAssign = function (taskId, element) {
        var labelEl   = document.getElementById('ckShiftAssignLabel');
        var taskIdInp = document.getElementById('ckShiftAssignTaskId');
        var availList = document.getElementById('shiftAssignAvailableList');
        var slotsPane = document.getElementById('slotAssignZones');

        // Task name from the clicked element's data attribute (HTML-decoded automatically).
        var taskName = (element && element.dataset) ? (element.dataset.taskName || '') : '';

        // Slot cells from the server-rendered bridge (window.CK_ShiftGrid).
        // Object.values() converts the time-keyed object into an ordered array of slot objects.
        var grid  = window.CK_ShiftGrid || {};
        var cells = grid[String(taskId)] || {};
        var slots = Object.values(cells);

        // Set modal context.
        if (labelEl)   { labelEl.textContent = taskName; }
        if (taskIdInp) { taskIdInp.value = String(taskId); }

        // Build left-column member pool.
        // All members are always shown — members may be assigned to multiple slots,
        // so the pool must never be filtered by existing assignments.
        if (availList) {
            availList.innerHTML = '';
            var members = (window.CK_EventDetail || {}).members || {};
            Object.values(members).forEach(function (m) {
                var li      = document.createElement('li');
                li.className        = 'ck-assign-item';
                li.dataset.memberId = String(m.id);
                var span    = document.createElement('span');
                span.className   = 'ck-assign-item__name';
                span.textContent = m.name;
                li.appendChild(span);
                availList.appendChild(li);
            });
            if (! availList.firstChild) {
                var emptyAvail       = document.createElement('li');
                emptyAvail.className = 'ck-assign-list__empty';
                emptyAvail.textContent = 'All members assigned';
                availList.appendChild(emptyAvail);
            }
        }

        // Build right-column slot zones.
        if (slotsPane) {
            slotsPane.innerHTML = '';

            slots.forEach(function (slot) {
                var assigned = Array.isArray(slot.assigned) ? slot.assigned : [];
                var cap      = parseInt(slot.capacity || 1, 10);
                var count    = assigned.length;
                var mod      = count >= cap ? '--full' : count > 0 ? '--partial' : '--empty';

                // Zone wrapper.
                var zone             = document.createElement('div');
                zone.className       = 'ck-slot-zone ck-slot-zone' + mod;
                zone.dataset.taskId  = String(taskId);
                zone.dataset.timeFrom = slot.time_from;
                zone.dataset.timeTo   = slot.time_to;
                zone.dataset.capacity = String(cap);

                // Zone header: time range + status badge.
                var header     = document.createElement('div');
                header.className = 'ck-slot-zone__header';

                var timeSpan         = document.createElement('span');
                timeSpan.className   = 'ck-slot-zone__time';
                timeSpan.textContent = slot.time_from + '–' + slot.time_to;

                var statusSpan          = document.createElement('span');
                statusSpan.className    = 'ck-slot-zone__status ck-slot-zone__status' + mod;
                statusSpan.dataset.slotKey = slot.time_from;
                statusSpan.textContent  = count + '/' + cap;

                header.appendChild(timeSpan);
                header.appendChild(statusSpan);
                zone.appendChild(header);

                // Drop list: assigned member chips + empty-state hint.
                var dropList              = document.createElement('ul');
                dropList.className        = 'ck-slot-drop';
                dropList.dataset.timeFrom = slot.time_from;
                dropList.dataset.timeTo   = slot.time_to;
                dropList.dataset.capacity = String(cap);

                if (assigned.length === 0) {
                    var emptyDrop       = document.createElement('li');
                    emptyDrop.className = 'ck-slot-drop__empty';
                    emptyDrop.textContent = '+ assign';
                    dropList.appendChild(emptyDrop);
                } else {
                    assigned.forEach(function (a) {
                        var li      = document.createElement('li');
                        li.className        = 'ck-assign-item';
                        li.dataset.etmId    = String(a.id);
                        li.dataset.memberId = String(a.member_id);
                        var span    = document.createElement('span');
                        span.className   = 'ck-assign-item__name';
                        span.textContent = a.name;
                        var btn     = document.createElement('button');
                        btn.type        = 'button';
                        btn.className   = 'ck-assign-item__remove';
                        btn.textContent = '×';
                        btn.title       = 'Remove';
                        li.appendChild(span);
                        li.appendChild(btn);
                        dropList.appendChild(li);
                    });
                }

                zone.appendChild(dropList);
                slotsPane.appendChild(zone);
            });
        }

        // Signal slot-modal.js to re-init SortableJS on the new drop zones.
        document.dispatchEvent(new CustomEvent('ck:shift.assign.open', { detail: { taskId: taskId } }));

        ckModalOpen('ckShiftAssignModal');
    };

    /**
     * Removes a timed member assignment (event_task_members with time_from set)
     * from the Einsatzplan grid.
     *
     * Called via onclick="event.stopPropagation(); ckSlotRemove(this)" on the
     * .ck-shift-chip__remove button in event-slots-panel.blade.php.
     *
     * event.stopPropagation() in the caller prevents the parent <td> onclick
     * (ckOpenShiftAssign) from firing. This function then executes the DELETE
     * directly, bypassing the document-level delegation in slot-modal.js.
     *
     * @param {HTMLButtonElement} btn  The × button (has data-slot-id).
     */
    window.ckSlotRemove = function (btn) {
        var slotId = btn && btn.dataset.slotId;
        if (! slotId || btn.disabled) { return; }

        var cfg = window.CK_EventDetail;
        if (! cfg || ! cfg.routes || ! cfg.routes.slotsBase) { return; }

        btn.disabled = true;

        // Ladeoverlay einblenden — _ckRefreshSlotPanel() blendet ihn aus.
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        fetch(cfg.routes.slotsBase + '/' + slotId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     cfg.csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                // AJAX Panel-Refresh statt voller Seitenneulade.
                // _ckRefreshSlotPanel wird von slot-modal.js auf window exponiert.
                if (typeof window._ckRefreshSlotPanel === 'function') {
                    window._ckRefreshSlotPanel();
                } else {
                    // Fallback falls slot-modal.js noch nicht initialisiert ist.
                    sessionStorage.setItem('ck_evt_active_tab', 'slots');
                    window.location.reload();
                }
            } else {
                btn.disabled = false;
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                if (typeof ckNotify === 'function') {
                    ckNotify('error', (window.ckUi && window.ckUi('saveError')) || 'Fehler beim Entfernen.');
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            if (typeof ckNotify === 'function') {
                ckNotify('error', (window.ckUi && window.ckUi('networkError')) || 'Netzwerkfehler.');
            }
        });
    };
}