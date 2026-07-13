/**
 * Functions-tab handlers (Funktionen tab on the event detail page).
 *
 * All mutations use _refreshFuncPanel() instead of reloadKeepingTab() —
 * an AJAX DOM-swap without a full page reload, following the same pattern
 * as slot-modal.js / _refreshSlotPanel().
 *
 * _refreshFuncPanel():
 *   GET /events/{id}/functions/panel-fragment  → JSON { panel: html, hero: html }
 *   → replaces #ckEvtPane-functions (functions pane)
 *   → replaces .ck-event-hero__right (hero right column in the Overview tab)
 *   → re-inits SortableJS + member selects for the new DOM elements
 *
 * Document-level delegated listeners (.ck-func-remove-btn, .ck-func-assign-select,
 * .ck-func-add-club-btn) are registered once and continue to work on the
 * replaced DOM automatically — no re-init required.
 *
 * @param {object} ctx - { cfg, csrf, Sortable, closest, reloadKeepingTab }
 */
export function initFunctionsTab(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var Sortable         = ctx.Sortable;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;  // Fallback on network error only.

    // ── Source-aware base URL ─────────────────────────────────────────────────

    function baseForSource(source) {
        return source === 'event'
            ? cfg.routes.eventFuncBase
            : cfg.routes.funcAssignBase;
    }

    // ── AJAX panel refresh (DOM-swap, no page reload) ─────────────────────────
    //
    // Identical pattern to slot-modal.js → _refreshSlotPanel().
    // Called after every successful mutation.

    function _refreshFuncPanel() {
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        fetch(cfg.routes.funcPanelFragment, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     csrf,
            },
        })
        .then(function (res) {
            if (! res.ok) { throw new Error('HTTP ' + res.status); }
            return res.json();
        })
        .then(function (data) {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }

            // Replace functions pane content.
            var funcPane = document.getElementById('ckEvtPane-functions');
            if (funcPane) {
                var tmp = document.createElement('div');
                tmp.innerHTML = data.panel;
                // Strip modal overlays — already teleported to #ck-modal-root on page load.
                tmp.querySelectorAll('.ck-modal-overlay').forEach(function (el) { el.remove(); });
                funcPane.innerHTML = tmp.innerHTML;
                // innerHTML does not execute <script> tags — re-create them manually.
                funcPane.querySelectorAll('script').forEach(function (old) {
                    var s = document.createElement('script');
                    s.textContent = old.textContent;
                    document.head.appendChild(s);
                    document.head.removeChild(s);
                });
            }

            // Replace hero right column (Overview tab).
            var heroRight = document.querySelector('.ck-event-hero__right');
            if (heroRight) { heroRight.innerHTML = data.hero; }

            // Re-init SortableJS and member selects for the new DOM elements.
            _initSortable();
            _populateMemberSelects();
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            reloadKeepingTab();  // Network error fallback.
        });
    }

    // ── SortableJS init (called on load and after every DOM-swap) ─────────────

    function _initSortable() {
        if (! Sortable) { return; }
        var availTbody    = document.getElementById('ckFuncAvailTbody');
        var assignedTbody = document.getElementById('ckFuncAssignedTbody');

        if (availTbody) {
            Sortable.create(availTbody, {
                group:       { name: 'ck-funcs', pull: 'clone', put: false },
                sort:        false,
                animation:   150,
                ghostClass:  'sortable-ghost',
                chosenClass: 'sortable-chosen',
            });
        }

        if (assignedTbody) {
            Sortable.create(assignedTbody, {
                group:     { name: 'ck-funcs', pull: false, put: true },
                sort:      false,
                animation: 150,

                onAdd: function (evt) {
                    var functionId = evt.item.dataset.functionId;
                    // Remove clone immediately — _refreshFuncPanel re-renders the row.
                    evt.item.remove();
                    if (! functionId) { return; }
                    _addClubFunction(functionId, null);
                },
            });
        }
    }

    // ── Populate member selects (called on load and after every DOM-swap) ─────

    function _populateMemberSelects() {
        var selects = document.querySelectorAll('.ck-func-assign-select');
        if (! selects.length || ! cfg.members) { return; }

        var members = Object.values(cfg.members);
        members.sort(function (a, b) { return (a.name || '').localeCompare(b.name || ''); });

        selects.forEach(function (sel) {
            var currentId = sel.dataset.currentMemberId;
            members.forEach(function (m) {
                var opt         = document.createElement('option');
                opt.value       = m.id;
                opt.textContent = m.name;
                if (String(m.id) === currentId) { opt.selected = true; }
                sel.appendChild(opt);
            });
        });
    }

    // ── Initial setup ─────────────────────────────────────────────────────────

    _initSortable();
    _populateMemberSelects();

    // ── Add club function to event (POST funcAddBase) ─────────────────────────

    function _addClubFunction(functionId, onError) {
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

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
            if (data.success) { _refreshFuncPanel(); }
            else {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                ckNotify('error', data.message || 'Failed to add function.');
                if (onError) { onError(); }
            }
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            ckNotify('error', 'Network error.');
            if (onError) { onError(); }
        });
    }

    // ── "+" button in the available club functions section (delegated) ─────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-func-add-club-btn');
        if (! btn) { return; }
        var functionId = btn.dataset.functionId;
        if (! functionId) { return; }
        btn.disabled = true;
        _addClubFunction(functionId, function () { btn.disabled = false; });
    });

    // ── Create ad-hoc event function (POST eventFuncBase) ─────────────────────

    var newEventFuncBtn = document.getElementById('newEventFuncSubmitBtn');

    if (newEventFuncBtn) {
        newEventFuncBtn.addEventListener('click', function () {
            var nameInput = document.getElementById('newEventFuncName');
            var name      = nameInput ? nameInput.value.trim() : '';
            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            newEventFuncBtn.disabled = true;
            if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

            fetch(cfg.routes.eventFuncBase, {
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
                    ckModalClose(null, 'newEventFuncModal');
                    _refreshFuncPanel();
                } else {
                    if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                    ckNotify('error', data.message || 'Failed to create function.');
                    newEventFuncBtn.disabled = false;
                }
            })
            .catch(function () {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                ckNotify('error', 'Network error.');
                newEventFuncBtn.disabled = false;
            });
        });
    }

    // ── Remove function from event (DELETE, source-aware, delegated) ──────────
    //
    // Club function  (source='club'):  removes pivot entry only;
    //                                  function reappears in the available section.
    // Event function (source='event'): permanently deletes from event_functions.

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-func-remove-btn');
        if (! btn) { return; }

        var functionId = btn.dataset.functionId;
        var source     = btn.dataset.source || 'club';
        if (! functionId) { return; }

        var confirmMsg = btn.dataset.ckConfirm;

        function doRemove() {
            btn.disabled = true;
            if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

            fetch(baseForSource(source) + '/' + functionId, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) { _refreshFuncPanel(); }
                else {
                    if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                    ckNotify('error', data.message || 'Failed to remove function.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                ckNotify('error', 'Network error.');
                btn.disabled = false;
            });
        }

        if (confirmMsg) { window.ckConfirm(confirmMsg, doRemove); }
        else            { doRemove(); }
    });

    // ── Assign member to function (PATCH, source-aware, delegated) ────────────

    document.addEventListener('change', function (e) {
        if (! e.target.matches('.ck-func-assign-select')) { return; }

        var sel        = e.target;
        var memberId   = sel.value;
        var functionId = sel.dataset.functionId;
        var source     = sel.dataset.source || 'club';
        if (! functionId) { return; }

        sel.disabled = true;
        if (typeof window.ckShowLoading === 'function') { window.ckShowLoading(); }

        fetch(baseForSource(source) + '/' + functionId, {
            method:  'PATCH',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept':           'application/json',
            },
            body: JSON.stringify({ member_id: memberId ? parseInt(memberId, 10) : null }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { _refreshFuncPanel(); }
            else {
                if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
                ckNotify('error', data.message || 'Failed to assign member.');
                sel.disabled = false;
            }
        })
        .catch(function () {
            if (typeof window.ckHideLoading === 'function') { window.ckHideLoading(); }
            ckNotify('error', 'Network error.');
            sel.disabled = false;
        });
    });
}