/**
 * ClubKit Users – Modal Logic
 * Erwartet window.CK_Users (gesetzt vom Blade-Data-Bridge-Script)
 */
(function () {
    'use strict';

    var data    = window.CK_Users || {};
    var routes  = data.routes  || {};
    var users   = data.users   || {};

    // ── DOM-Refs ────────────────────────────────────────────────────────────
    var modal      = document.getElementById('userModal');
    var titleEl    = document.getElementById('modalTitle');

    var loginForm  = document.getElementById('userLoginForm');
    var rightsForm = document.getElementById('userRightsForm');
    var loginMethod= document.getElementById('loginFormMethod');

    var fName      = document.getElementById('fieldName');
    var fEmail     = document.getElementById('fieldEmail');
    var fPassword  = document.getElementById('fieldPassword');
    var pwHint     = document.getElementById('passwordHint');
    var pwConfirmRow = document.getElementById('passwordConfirmRow');

    // ── Öffnen ──────────────────────────────────────────────────────────────
    window.openUserModal = function (mode, userId) {
        userId = userId || null;

        if (mode === 'create') {
            titleEl.textContent     = 'Neuen Nutzer anlegen';
            fName.value             = '';
            fEmail.value            = '';
            fPassword.value         = '';
            fPassword.required      = true;
            if (pwHint) pwHint.style.display = 'none';
            loginMethod.value       = 'POST';
            loginForm.action        = routes.store  || '';
            rightsForm.action       = routes.store  || '';
        } else {
            var u = users[userId];
            if (!u) return;
            titleEl.textContent     = u.name + ' bearbeiten';
            fName.value             = u.name;
            fEmail.value            = u.email;
            fPassword.value         = '';
            fPassword.required      = false;
            if (pwHint) pwHint.style.display = '';
            loginMethod.value       = 'PATCH';
            loginForm.action        = routes.update + '/' + userId;
            rightsForm.action       = routes.update + '/' + userId;

            // Rollen vorauswählen
            document.querySelectorAll('input[name="role"]').forEach(function (r) { r.checked = false; });
            document.querySelectorAll('input[name="permissions[]"]').forEach(function (p) { p.checked = false; });
            document.getElementById('customPermissions').style.display = 'none';

            if (u.roles.length > 0) {
                var roleInput = document.getElementById('role_' + u.roles[0]);
                if (roleInput) { roleInput.checked = true; highlightRole(u.roles[0]); }
            } else if (u.permissions.length > 0) {
                var custom = document.getElementById('role_custom');
                if (custom) { custom.checked = true; highlightRole('custom'); }
                document.getElementById('customPermissions').style.display = '';
                u.permissions.forEach(function (pName) {
                    document.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                        if (cb.value === pName) cb.checked = true;
                    });
                });
            }
        }

        switchUserTab('login');
        modal.style.display          = 'flex';
        document.body.style.overflow = 'hidden';
    };

    // ── Schließen ───────────────────────────────────────────────────────────
    window.closeUserModal = function (e) {
        if (e && e.target !== modal) return;
        modal.style.display          = 'none';
        document.body.style.overflow = '';
    };

    // ── Tab-Wechsel ─────────────────────────────────────────────────────────
    window.switchUserTab = function (tab) {
        var loginTab   = document.getElementById('tabLogin');
        var rightsTab  = document.getElementById('tabRights');
        var loginBtn   = document.getElementById('tabLoginBtn');
        var rightsBtn  = document.getElementById('tabRightsBtn');

        if (tab === 'login') {
            loginTab.style.display       = '';
            rightsTab.style.display      = 'none';
            loginBtn.style.color         = '#0a1628';
            loginBtn.style.borderBottom  = '2px solid #0a1628';
            rightsBtn.style.color        = '#64748b';
            rightsBtn.style.borderBottom = '2px solid transparent';
        } else {
            loginTab.style.display       = 'none';
            rightsTab.style.display      = '';
            loginBtn.style.color         = '#64748b';
            loginBtn.style.borderBottom  = '2px solid transparent';
            rightsBtn.style.color        = '#0a1628';
            rightsBtn.style.borderBottom = '2px solid #0a1628';
        }
    };

    // ── Rollen-Highlight ────────────────────────────────────────────────────
    window.onRoleChange = function (input) {
        highlightRole(input.value);
        document.getElementById('customPermissions').style.display =
            input.value === 'custom' ? '' : 'none';
    };

    function highlightRole(value) {
        document.querySelectorAll('[id^="roleLabel_"]').forEach(function (el) {
            el.style.borderColor     = '#e2e8f0';
            el.style.backgroundColor = '#f8fafc';
        });
        var target = document.getElementById('roleLabel_' + value);
        if (target) {
            target.style.borderColor     = value === 'custom' ? '#7c3aed' : '#1a6fc4';
            target.style.backgroundColor = value === 'custom' ? '#faf5ff' : '#eff6ff';
        }
    }

    // ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal && modal.style.display === 'flex') {
            modal.style.display          = 'none';
            document.body.style.overflow = '';
        }
    });

}());
