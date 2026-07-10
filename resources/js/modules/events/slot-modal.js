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
                        // A member may be assigned to multiple DIFFERENT slots, but not
                        // twice to the exact same slot. The [data-etm-id] selector matches
                        // only persisted chips (not the temporary clone).
                        var alreadyHere = !! dropList.querySelector(
                            '.ck-assign-item[data-etm-id][data-member-id="' + memberId + '"]'
                        );
                        if (alreadyHere) {
                            item.remove();
                            ckNotify('warning', window.ckUi('alreadyAssigned') || 'Mitglied ist diesem Slot bereits zugewiesen.');
                            return;
                        }

                        // Remove the clone; replaced by a real chip after AJAX success.
                        item.remove();

                        var taskIdInp = document.getElementById('ckShiftAssignTaskId');
                        if (! taskIdInp) { return; }

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
                            if (data.success && data.id) {
                                var members = (window.CK_EventDetail || {}).members || {};
                                var name    = members[memberId]
                                    ? members[memberId].name : 'Member';

                                // Insert the permanent chip into the drop zone.
                                // Note: the available pool is intentionally NOT modified —
                                // members may be assigned to multiple slots, so they must
                                // remain visible in the pool at all times.
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

                            } else if (data.error === 'already_assigned') {
                                ckNotify('warning', window.ckUi('alreadyAssigned'));
                            } else {
                                ckNotify('error', data.message || window.ckUi('saveError'));
                            }
                        })
                        .catch(function () {
                            ckNotify('error', window.ckUi('networkError'));
                        });
                    },
                })
            );
        });
    });

    // ── 2a. Remove member from slot — × button click (delegated) ─────────────
    //
    // Scoped to #slotAssignZones. No page reload: chip removed, member back in pool.

    document.addEventListener('click', function (e) {
        var slotsPane = document.getElementById('slotAssignZones');
        if (! slotsPane || ! slotsPane.contains(e.target)) { return; }

        var removeBtn = closest(e.target, '.ck-assign-item__remove');
        if (! removeBtn) { return; }
        var item  = closest(removeBtn, '.ck-assign-item');
        if (! item) { return; }
        var etmId = item.dataset.etmId;
        if (! etmId) { return; }

        var dropList = closest(item, '.ck-slot-drop');
        var timeFrom = dropList ? dropList.dataset.timeFrom : null;

        item.classList.add('ck-assign-item--pending');

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
            if (data.success) {
                var memberId = item.dataset.memberId;
                var members  = (window.CK_EventDetail || {}).members || {};
                var member   = members[memberId];

                item.remove();
                if (timeFrom) { _refreshSlotZone(timeFrom); }
                // Note: pool is not modified on remove — member is already
                // visible there (pool always shows all members).
            } else {
                item.classList.remove('ck-assign-item--pending');
                ckNotify('error', window.ckUi('saveError'));
            }
        })
        .catch(function () {
            item.classList.remove('ck-assign-item--pending');
            ckNotify('error', window.ckUi('networkError'));
        });
    });

    // ── 2b. Done button: close modal + reload keeping current tab ─────────────

    var shiftAssignDoneBtn = document.getElementById('ckShiftAssignDoneBtn');
    if (shiftAssignDoneBtn) {
        shiftAssignDoneBtn.addEventListener('click', function () {
            ckModalClose(null, 'ckShiftAssignModal');
            reloadKeepingTab();
        });
    }

    // ── 3. Remove slot (delegated, any .ck-slot-remove-btn in the DOM) ────────
    // DELETE /events/{event}/slots/{slotId}

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-slot-remove-btn');
        if (! btn) { return; }

        var slotId = btn.dataset.slotId;
        if (! slotId) { return; }

        // Prevent the parent cell's onclick="ckOpenEinsatzAssign(...)" from firing.
        e.stopPropagation();

        btn.disabled = true;

        fetch(cfg.routes.slotsBase + '/' + slotId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { reloadKeepingTab(); }
            else              { btn.disabled = false; }
        })
        .catch(function () { btn.disabled = false; });
    });

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