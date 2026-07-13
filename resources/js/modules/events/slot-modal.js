/**
 * Shift plan slot handlers:
 *
 *   1. ckShiftConfigModal  — PATCH /events/{event}/tasks/{taskId}/slot-config
 *      Opens via ckOpenShiftConfig(taskId) defined in globals.js.
 *
 *   2. ckShiftAssignModal  — POST /events/{event}/slots {event_task_id, member_id, time_from, time_to}
 *      Opens via ckOpenShiftAssign(taskId, taskName, allSlots) defined in globals.js.
 *
 *   3. .ck-slot-remove-btn   — DELETE /events/{event}/slots/{slotId}
 *      Delegated handler on document.
 *
 *   (Legacy) slotModal — kept for backward compatibility; the new grid panel no longer
 *   triggers it, but the modal still exists in show.blade.php.
 *
 * @param {object} ctx - Shared context { cfg, csrf, closest, reloadKeepingTab }
 */
export function initSlotModal(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    /**
     * Dirty flag: true after any assignment (POST) or removal (DELETE) in the modal.
     * Reset to false on modal open and after a successful AJAX panel refresh.
     * Used by the outside-click interceptor to prompt "reload now?" before close.
     */
    var _slotDirty = false;

    // ── Modal lock: prevents race conditions when requests overlap ───────────
    //
    // While a request (POST assign / DELETE remove) is in flight:
    //   - No further request is accepted (_slotRequestPending guard at the top of each handler).
    //   - The modal content is locked via .ck-modal-content--busy (pointer-events: none).
    //   - A spinner + semi-transparent overlay is shown via CSS ::before / ::after.
    //
    // Drag events are checked at the start of onAdd — the SortableJS clone is
    // discarded immediately (evt.item.remove()) to avoid orphaned DOM nodes.

    var _slotRequestPending = false;

    function _modalLock() {
        _slotRequestPending = true;
        var modal   = document.getElementById('ckShiftAssignModal');
        var content = modal ? modal.querySelector('.ck-modal-content') : null;
        if (content) { content.classList.add('ck-modal-content--busy'); }
    }

    function _modalUnlock() {
        _slotRequestPending = false;
        var modal   = document.getElementById('ckShiftAssignModal');
        var content = modal ? modal.querySelector('.ck-modal-content') : null;
        if (content) { content.classList.remove('ck-modal-content--busy'); }
    }

    // ── 1. Shift plan config modal submit ────────────────────────────────────
    // PATCH /events/{event}/tasks/{taskId}/slot-config

    var configSubmitBtn = document.getElementById('ckShiftConfigSubmitBtn');

    if (configSubmitBtn) {
        configSubmitBtn.addEventListener('click', function () {
            var taskSel      = document.getElementById('ckShiftConfigTaskId');
            var startInp     = document.getElementById('ckShiftConfigStart');
            var endInp       = document.getElementById('ckShiftConfigEnd');
            var intervalSel  = document.getElementById('ckShiftConfigInterval');
            var capacityInp  = document.getElementById('ckShiftConfigCapacity');

            var taskId   = taskSel    ? taskSel.value   : '';
            var start    = startInp   ? startInp.value  : '';
            var end      = endInp     ? endInp.value     : '';
            var interval = intervalSel ? intervalSel.value : '';
            var capacity = capacityInp ? capacityInp.value : '';

            // Validate required fields.
            var hasError = false;
            [taskSel, startInp, endInp, intervalSel, capacityInp].forEach(function (el) {
                if (el && ! el.value) {
                    el.classList.add('ck-input--error');
                    hasError = true;
                } else if (el) {
                    el.classList.remove('ck-input--error');
                }
            });
            if (hasError) { return; }

            // Zeitkonsistenz: Endzeit muss nach Startzeit liegen (H:i-Strings sind lexikographisch vergleichbar).
            if (start && end && end <= start) {
                if (endInp) { endInp.classList.add('ck-input--error'); }
                ckNotify('warning', 'Die Endzeit muss nach der Startzeit liegen.');
                return;
            }

            configSubmitBtn.disabled = true;

            // Build PATCH URL: /events/{event}/tasks/{taskId}/slot-config
            var url = cfg.routes.tasksBase + '/' + parseInt(taskId, 10) + '/slot-config';

            fetch(url, {
                method:  'PATCH',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({
                    slot_start_time:       start,
                    slot_end_time:         end,
                    slot_interval_minutes: parseInt(interval, 10),
                    slot_capacity:         parseInt(capacity,  10),
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    reloadKeepingTab();
                } else {
                    var errMsg = data.error
                        || data.message
                        || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                        || window.ckUi('saveError');
                    ckNotify('error', errMsg);
                    configSubmitBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', window.ckUi('networkError'));
                configSubmitBtn.disabled = false;
            });
        });
    }

    // ── 2. Shift-assignment modal — multi-slot drop-zones ────────────────────
    //
    // Layout:
    //   Left  = member pool: members not assigned to ANY slot of this task
    //   Right = all slots of the task as individual SortableJS drop-zones
    //
    // ckOpenShiftAssign() in globals.js builds the DOM then dispatches
    // 'ck:shift.assign.open' to trigger SortableJS init here.
    //
    // Zones are rebuilt on every open (dynamic per task) → teardown + re-init.
    //
    // AJAX: POST   /events/{event}/slots          → assign member to slot
    //       DELETE /events/{event}/slots/{etmId}  → remove from slot

    var _einsatzSortables = []; // active SortableJS instances for the current open

    /**
     * Destroys all SortableJS instances from the previous modal open.
     */
    function _destroyEinsatzSortables() {
        _einsatzSortables.forEach(function (s) {
            try { s.destroy(); } catch (ex) { /* already destroyed */ }
        });
        _einsatzSortables = [];
    }

    /**
     * Refreshes the status badge and modifier class on a slot zone after an
     * assign or remove operation.
     *
     * @param {string} timeFrom - H:i slot start (data-time-from on the drop list).
     */
    function _refreshSlotZone(timeFrom) {
        var slotsPane = document.getElementById('slotAssignZones');
        if (! slotsPane) { return; }
        var dropList = slotsPane.querySelector(
            '.ck-slot-drop[data-time-from="' + timeFrom + '"]'
        );
        if (! dropList) { return; }

        var cap   = parseInt(dropList.dataset.capacity || 1, 10);
        var count = dropList.querySelectorAll('.ck-assign-item[data-etm-id]').length;
        var zone  = dropList.parentElement;

        // Update badge text + colour class.
        var badge = zone ? zone.querySelector('.ck-slot-zone__status') : null;
        if (badge) {
            badge.textContent = count + '/' + cap;
            badge.className   = 'ck-slot-zone__status'
                + (count >= cap ? ' ck-slot-zone__status--full'
                    : count > 0  ? ' ck-slot-zone__status--partial'
                                 : ' ck-slot-zone__status--empty');
        }
        if (zone) {
            zone.classList.remove('ck-slot-zone--full', 'ck-slot-zone--partial', 'ck-slot-zone--empty');
            zone.classList.add(
                count >= cap ? 'ck-slot-zone--full'
                    : count > 0  ? 'ck-slot-zone--partial'
                                 : 'ck-slot-zone--empty'
            );
        }

        // Show / hide the empty-state drop hint.
        var emptyLi  = dropList.querySelector('.ck-slot-drop__empty');
        var hasItems = !! dropList.querySelector('.ck-assign-item[data-etm-id]');
        if (hasItems && emptyLi)    { emptyLi.remove(); }
        if (! hasItems && ! emptyLi) {
            var newEmpty       = document.createElement('li');
            newEmpty.className = 'ck-slot-drop__empty';
            newEmpty.textContent = '+ assign';
            dropList.appendChild(newEmpty);
        }
    }

    // ── Re-init SortableJS on every modal open (CustomEvent from globals.js) ──

    document.addEventListener('ck:shift.assign.open', function () {
        _destroyEinsatzSortables();
        _slotDirty           = false;  // Fresh session — reset dirty flag.
        _slotRequestPending  = false;  // Safety reset in case a previous request left the lock set (e.g. network error).

        var availList = document.getElementById('shiftAssignAvailableList');
        var slotsPane = document.getElementById('slotAssignZones');
        if (! availList || ! slotsPane || ! Sortable) { return; }

        // Available list: drag source in clone mode.
        _einsatzSortables.push(
            Sortable.create(availList, {
                group: { name: 'einsatz-slots', pull: 'clone', put: false },
                sort:  false,
            })
        );

        // One Sortable per slot drop-zone.
        slotsPane.querySelectorAll('.ck-slot-drop').forEach(function (dropList) {
            _einsatzSortables.push(
                Sortable.create(dropList, {
                    group: { name: 'einsatz-slots', pull: false, put: true },
                    sort:  false,

                    /**
                     * Fires when a member item is dropped into this slot zone.
                     * Removes the clone immediately, then fires a POST; on success
                     * inserts a permanent chip with the new event_task_member id.
                     */
                    onAdd: function (evt) {
                        // Race-condition guard: reject new drags while a request is in flight.
                        if (_slotRequestPending) {
                            evt.item.remove();  // Discard the SortableJS clone immediately.
                            return;
                        }

                        var item     = evt.item;
                        var memberId = parseInt(item.dataset.memberId, 10);
                        var timeFrom = dropList.dataset.timeFrom;
                        var timeTo   = dropList.dataset.timeTo;
                        var cap      = parseInt(dropList.dataset.capacity || 1, 10);

                        // Capacity check.
                        var count = dropList.querySelectorAll('.ck-assign-item[data-etm-id]').length;
                        if (count >= cap) {
                            item.remove();
                            ckNotify('warning', 'Capacity (' + cap + ') already reached.');
                            return;
                        }

                        // Duplicate-in-same-slot guard.
                        var alreadyHere = !! dropList.querySelector(
                            '.ck-assign-item[data-etm-id][data-member-id="' + memberId + '"]'
                        );
                        if (alreadyHere) {
                            item.remove();
                            ckNotify('warning', window.ckUi('alreadyAssigned') || 'Member is already assigned to this slot.');
                            return;
                        }

                        // Remove the clone — the real chip is inserted after AJAX success.
                        item.remove();

                        var taskIdInp = document.getElementById('ckShiftAssignTaskId');
                        if (! taskIdInp) { return; }

                        // Lock the modal — shows spinner and disables pointer-events until response.
                        _modalLock();

                        fetch(cfg.routes.slotsBase, {
                            method:  'POST',
                            headers: {
                                'Content-Type':     'application/json',
                                'X-CSRF-TOKEN':     csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept':           'application/json',
                            },
                            body: JSON.stringify({
                                event_task_id: parseInt(taskIdInp.value, 10),
                                member_id:     memberId,
                                time_from:     timeFrom,
                                time_to:       timeTo,
                            }),
                        })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            _modalUnlock();  // Always unlock, regardless of success or error.

                            if (data.success && data.id) {
                                var members = (window.CK_EventDetail || {}).members || {};
                                var name    = members[memberId]
                                    ? members[memberId].name : 'Member';

                                // Insert the permanent chip into the drop zone.
                                // The pool remains unchanged — members may be assigned to multiple slots.
                                var li  = document.createElement('li');
                                li.className        = 'ck-assign-item';
                                li.dataset.etmId    = String(data.id);
                                li.dataset.memberId = String(memberId);
                                var span    = document.createElement('span');
                                span.className   = 'ck-assign-item__name';
                                span.textContent = name;
                                var btn     = document.createElement('button');
                                btn.type        = 'button';
                                btn.className   = 'ck-assign-item__remove';
                                btn.textContent = '×';
                                btn.title       = 'Remove';
                                li.appendChild(span);
                                li.appendChild(btn);
                                dropList.appendChild(li);

                                _refreshSlotZone(timeFrom);
                                _slotDirty = true;

                            } else if (data.error === 'already_assigned') {
                                ckNotify('warning', window.ckUi('alreadyAssigned'));
                            } else {
                                ckNotify('error', data.message || window.ckUi('saveError'));
                            }
                        })
                        .catch(function () {
                            _modalUnlock();
                            ckNotify('error', window.ckUi('networkError'));
                        });
                    },
                })
            );
        });
    });

    // ── 2a. Remove member from slot — × button click ──────────────────────────
    //
    // CRITICAL: Attached on #slotAssignZones, NOT on document.
    //
    // Root cause of the previous bug:
    //   .ck-modal-content has onclick="event.stopPropagation()".
    //   This blocks ALL clicks inside the modal from reaching document-level
    //   handlers. #slotAssignZones is INSIDE .ck-modal-content but bubbles
    //   to it BEFORE the stopPropagation fires — so a listener here works.
    //
    // No page reload: chip removed optimistically from modal DOM.
    // _slotDirty = true so Done / outside-click prompts an AJAX panel refresh.

    var _slotsPane = document.getElementById('slotAssignZones');
    if (_slotsPane) {
        _slotsPane.addEventListener('click', function (e) {
            var removeBtn = closest(e.target, '.ck-assign-item__remove');
            if (! removeBtn) { return; }

            // Race-condition guard: reject clicks while a request is in flight.
            if (_slotRequestPending) { return; }

            var item  = closest(removeBtn, '.ck-assign-item');
            if (! item) { return; }
            var etmId = item.dataset.etmId;
            if (! etmId) { return; }

            var dropList = closest(item, '.ck-slot-drop');
            var timeFrom = dropList ? dropList.dataset.timeFrom : null;

            item.classList.add('ck-assign-item--pending');
            _modalLock();  // Lock modal — shows spinner, disables pointer-events.

            fetch(cfg.routes.slotsBase + '/' + etmId, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                _modalUnlock();
                if (data.success) {
                    item.remove();
                    if (timeFrom) { _refreshSlotZone(timeFrom); }
                    _slotDirty = true;
                } else {
                    item.classList.remove('ck-assign-item--pending');
                    ckNotify('error', window.ckUi('saveError'));
                }
            })
            .catch(function () {
                _modalUnlock();
                item.classList.remove('ck-assign-item--pending');
                ckNotify('error', window.ckUi('networkError'));
            });
        });
    }

    // ── 2b. AJAX panel refresh ────────────────────────────────────────────────
    //
    // Fetches GET /events/{event}/slots/panel-fragment and replaces the
    // #ckEvtPane-slots content without a full page reload. Inline <script>
    // tags (e.g. window.CK_ShiftGrid update) are re-executed manually.
    //
    // Falls back to reloadKeepingTab() on network / server error.

    function _refreshSlotPanel() {
        // Show the full-page loading overlay while the request is in flight.
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        fetch(cfg.routes.slotsBase + '/panel-fragment', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'text/html',
                'X-CSRF-TOKEN':     csrf,
            },
        })
        .then(function (res) {
            if (! res.ok) { throw new Error('HTTP ' + res.status); }
            return res.text();
        })
        .then(function (html) {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }

            var slotPane = document.getElementById('ckEvtPane-slots');
            if (! slotPane) { reloadKeepingTab(); return; }

            // CRITICAL: Strip modal overlays from the fragment BEFORE injecting into the DOM.
            //
            // The panel HTML contains ckShiftAssignModal + ckShiftConfigModal.
            // app.js teleports all .ck-modal-overlay to #ck-modal-root on page load (once,
            // with all event handlers attached). Re-injecting them creates duplicate IDs —
            // getElementById() then resolves to the wrong (empty, non-teleported) element,
            // breaking all handlers and modal-open calls.
            var tmp       = document.createElement('div');
            tmp.innerHTML = html;
            tmp.querySelectorAll('.ck-modal-overlay').forEach(function (el) { el.remove(); });
            slotPane.innerHTML = tmp.innerHTML;

            // innerHTML does not execute <script> tags — re-create them so that
            // window.CK_ShiftGrid and similar data bridges are updated.
            slotPane.querySelectorAll('script').forEach(function (oldScript) {
                var newScript         = document.createElement('script');
                newScript.textContent = oldScript.textContent;
                document.head.appendChild(newScript);
                document.head.removeChild(newScript);
            });

            _slotDirty = false;
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            reloadKeepingTab();
        });
    }

    // Expose _refreshSlotPanel globally so window.ckSlotRemove (globals.js) can
    // call it without a full page reload. Prefixed with underscore to signal
    // "internal, not for general use outside this module family".
    window._ckRefreshSlotPanel = _refreshSlotPanel;

    // ── 2c. Speichern button: close modal + AJAX panel refresh ────────────────

    var shiftAssignDoneBtn = document.getElementById('ckShiftAssignDoneBtn');
    if (shiftAssignDoneBtn) {
        shiftAssignDoneBtn.addEventListener('click', function () {
            // Do not close while a request is still in flight.
            if (_slotRequestPending) { return; }
            ckModalClose(null, 'ckShiftAssignModal');
            _slotDirty = false;
            _refreshSlotPanel();
        });
    }

    // ── 2d. Outside-click interception — confirm before closing with dirty state
    //
    // The overlay's inline onclick="ckModalClose(event, 'ckShiftAssignModal')"
    // fires when the user clicks the backdrop. To intercept BEFORE that inline
    // handler runs, we use useCapture: true on document. capture-phase handlers
    // at document level run before target-phase handlers at the element.
    //
    // If dirty: show confirm dialog → user can apply (refresh + close) or cancel.
    // If clean: do nothing → ckModalClose runs normally.

    document.addEventListener('click', function (e) {
        var assignModal = document.getElementById('ckShiftAssignModal');
        if (! assignModal) { return; }
        if (! assignModal.classList.contains('ck-modal--open')) { return; }
        if (e.target !== assignModal) { return; }  // Only backdrop, not modal content
        if (! _slotDirty) { return; }              // Nothing to confirm

        e.stopPropagation();  // Prevent ckModalClose from firing

        window.ckConfirm(
            'Es wurden Zuweisungen gespeichert, die noch nicht im Einsatzplan sichtbar sind. Jetzt aktualisieren?',
            function () {
                ckModalClose(null, 'ckShiftAssignModal');
                _refreshSlotPanel();
            }
        );
    }, true);  // useCapture → runs before overlay's inline onclick

    // ── 3. Grid-× remove: handled by window.ckSlotRemove (globals.js) ─────────
    // .ck-shift-chip__remove uses onclick="event.stopPropagation(); ckSlotRemove(this)"
    // and triggers ckSlotRemove directly — no document-level delegation needed.
    // The old section-3 document listener has been removed.

    // ── (Legacy) Old slotModal handler ────────────────────────────────────────
    // The slotModal is still present in show.blade.php (not removed from Events module).
    // It is no longer triggered by the new Einsatzplan grid panel.
    // Kept here so any external trigger (e.g., a future quick-add button) still works.

    var legacySlotBtn = document.getElementById('slotModalSubmitBtn');

    if (legacySlotBtn) {
        legacySlotBtn.addEventListener('click', function () {
            var taskSel  = document.getElementById('slotModalTaskId');
            var memSel   = document.getElementById('slotModalMemberId');
            var fromInp  = document.getElementById('slotModalTimeFrom');
            var toInp    = document.getElementById('slotModalTimeTo');

            var taskId   = taskSel  ? taskSel.value  : '';
            var memberId = memSel   ? memSel.value   : '';
            var timeFrom = fromInp  ? fromInp.value  : '';
            var timeTo   = toInp    ? toInp.value    : '';

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

            legacySlotBtn.disabled = true;

            fetch(cfg.routes.slotsBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({
                    event_task_id: parseInt(taskId,   10),
                    member_id:     parseInt(memberId, 10),
                    time_from:     timeFrom,
                    time_to:       timeTo,
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || window.ckUi('saveError'));
                    legacySlotBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', window.ckUi('networkError'));
                legacySlotBtn.disabled = false;
            });
        });
    }
}