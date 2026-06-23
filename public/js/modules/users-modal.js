/**
 * ClubKit Users – Modal Logic
 * Erwartet window.CK_Users (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 */
(function () {
    'use strict';

    var cfg    = window.CK_Users || {};
    var users  = cfg.users  || {};
    var routes = cfg.routes || {};

    var loginForm   = document.getElementById('userLoginForm');
    var rightsForm  = document.getElementById('userRightsForm');
    var loginMethod = document.getElementById('userLoginMethod');
    var titleEl     = document.getElementById('userModal-title');

    /**
     * Modal öffnen
     * @param {string}     mode   – 'create' | 'edit'
     * @param {number|null} userId
     */
    window.usersModalOpen = function (mode, userId) {
        userId = userId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neuen Nutzer anlegen';
            _setField('fieldName',     '');
            _setField('fieldEmail',    '');
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = true;
            loginMethod.value   = 'POST';
            loginForm.action    = routes.store || '';
            rightsForm.action   = routes.store || '';
        } else {
            var u = users[userId];
            if (!u) return;
            if (titleEl) titleEl.textContent = u.name + ' bearbeiten';
            _setField('fieldName',     u.name);
            _setField('fieldEmail',    u.email);
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = false;
            loginMethod.value   = 'PATCH';
            loginForm.action    = (routes.update || '') + '/' + userId;
            rightsForm.action   = (routes.update || '') + '/' + userId;

            _applyRights(u);
        }

        // Ersten Tab aktivieren
        var firstTabBtn = document.getElementById('userTab-login-btn');
        if (firstTabBtn) ckModalTab('userModal', 'userTab-login', firstTabBtn);

        ckModalOpen('userModal');
    };

    /**
     * Rollen-Radio onChange
     */
    window.usersRoleChanged = function (input) {
        // Alle Rollen-Labels zurücksetzen
        document.querySelectorAll('.ck-role-option').forEach(function (el) {
            el.classList.remove('ck-role-option--selected');
            el.classList.remove('ck-role-option--selected-custom');
        });

        // Aktives Label markieren
        var label = document.getElementById('roleOption-' + input.value);
        if (label) {
            if (input.value === 'custom') {
                label.classList.add('ck-role-option--selected-custom');
            } else {
                label.classList.add('ck-role-option--selected');
            }
        }

        // Custom-Permissions anzeigen/verstecken
        var customBlock = document.getElementById('customPermissions');
        if (customBlock) {
            if (input.value === 'custom') {
                customBlock.classList.remove('is-hidden');
            } else {
                customBlock.classList.add('is-hidden');
            }
        }
    };

    // ── Private Helpers ──────────────────────────────────────────────────────

    function _setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function _applyRights(user) {
        // Alle Radios zurücksetzen
        document.querySelectorAll('input[name="role"]').forEach(function (r) { r.checked = false; });
        document.querySelectorAll('input[name="permissions[]"]').forEach(function (p) { p.checked = false; });
        document.querySelectorAll('.ck-role-option').forEach(function (el) {
            el.classList.remove('ck-role-option--selected', 'ck-role-option--selected-custom');
        });

        var customBlock = document.getElementById('customPermissions');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (user.roles.length > 0) {
            var roleInput = document.getElementById('roleRadio-' + user.roles[0]);
            if (roleInput) {
                roleInput.checked = true;
                usersRoleChanged(roleInput);
            }
        } else if (user.permissions.length > 0) {
            var customInput = document.getElementById('roleRadio-custom');
            if (customInput) {
                customInput.checked = true;
                usersRoleChanged(customInput);
            }
            user.permissions.forEach(function (pName) {
                document.querySelectorAll('input[name="permissions[]"]').forEach(function (cb) {
                    if (cb.value === pName) cb.checked = true;
                });
            });
        }
    }

}());
