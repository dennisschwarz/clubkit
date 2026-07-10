/**
 * Funktionen-Tab handlers:
 *   - Populate function select (available functions)
 *   - Add function to event (POST)
 *   - Remove function from event (DELETE)
 *   - Assign member to function (PATCH)
 *
 * @param {object} ctx - Shared context { cfg, csrf, closest, reloadKeepingTab }
 */
export function initFunctionsTab(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    // ── Populate function select ───────────────────────────────────────────────

    (function () {
        var funcSel = document.getElementById('newFuncSelect');
        if (! funcSel || ! cfg.availableFunctions) { return; }
        Object.values(cfg.availableFunctions).forEach(function (fn) {
            var opt         = document.createElement('option');
            opt.value       = fn.id;
            opt.textContent = fn.name;
            funcSel.appendChild(opt);
        });
    }());

    // ── Add function to event ─────────────────────────────────────────────────

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
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || 'Fehler beim Hinzuf\u00fcgen der Funktion.');
                    newFuncBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newFuncBtn.disabled = false;
            });
        });
    }

    // ── Remove function from event ────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-func-remove-btn');
        if (! btn) { return; }

        var functionId = btn.dataset.functionId;
        if (! functionId) { return; }

        var confirmMsg = btn.dataset.ckConfirm;

        function doRemove() {
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
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || 'Fehler beim Entfernen der Funktion.');
                    btn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                btn.disabled = false;
            });
        }

        if (confirmMsg) { window.ckConfirm(confirmMsg, doRemove); }
        else            { doRemove(); }
    });

    // ── Assign member to function ─────────────────────────────────────────────

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
            body: JSON.stringify({ member_id: memberId ? parseInt(memberId, 10) : null }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { reloadKeepingTab(); }
            else {
                ckNotify('error', data.message || 'Fehler beim Zuweisen der Person.');
                sel.disabled = false;
            }
        })
        .catch(function () {
            ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
            sel.disabled = false;
        });
    });
}
