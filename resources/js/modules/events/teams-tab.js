/**
 * Teams-Tab handlers:
 *   - Add team to event (POST)
 *   - Remove team from event (DELETE)
 *
 * @param {object} ctx - Shared context { cfg, csrf, closest, reloadKeepingTab }
 */
export function initTeamsTab(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    // ── Add team ──────────────────────────────────────────────────────────────

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
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || 'Fehler beim Hinzuf\u00fcgen des Teams.');
                    teamAddBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                teamAddBtn.disabled = false;
            });
        });
    }

    // ── Remove team ───────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-team-remove-btn');
        if (! btn) { return; }

        var teamId = btn.dataset.teamId;
        if (! teamId) { return; }

        var confirmMsg = btn.dataset.ckConfirm;

        function doRemove() {
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
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || 'Fehler beim Entfernen des Teams.');
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
}
