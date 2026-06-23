/**
 * ClubKit Users – Modal Logic
 * Erwartet window.CK_Users (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 *
 * BUG-FIX:
 *   Vorher: "+ Nutzer anlegen" → ckModalOpen() direkt → kein action/method gesetzt
 *           → Formular sendet PATCH an aktuelle URL → MethodNotAllowed
 *   Jetzt:  "+ Nutzer anlegen" → usersModalOpen('create') → korrekte action/method
 *           → Rights-Tab in create-Modus deaktiviert (kein userId für Rechte)
 */
(function () {
    'use strict';

    var cfg    = window.CK_Users || {};
    var users  = cfg.users  || {};
    var routes = cfg.routes || {};

    var loginForm    = document.getElementById('userLoginForm');
    var rightsForm   = document.getElementById('userRightsForm');
    var loginMethod  = document.getElementById('userLoginMethod');
    var rightsMethod = document.getElementById('userRightsMethod');
    var titleEl      = document.getElementById('userModal-title');

    // Tabs + Hinweis
    var rightsTabBtn   = document.getElementById('userTab-rights-btn');
    var createHint     = document.getElementById('userRightsCreateHint');

    /**
     * Modal öffnen.
     * @param {string}      mode    'create' | 'edit'
     * @param {number|null} userId
     */
    window.usersModalOpen = function (mode, userId) {
        userId = userId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neuen Nutzer anlegen';

            // Login-Formular auf POST (neu anlegen)
            _setField('fieldName',     '');
            _setField('fieldEmail',    '');
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = true;
            loginMethod.value  = 'POST';
            loginForm.action   = routes.store || '';

            // Rights-Tab deaktivieren – Nutzer muss erst existieren
            _setRightsTabEnabled(false);

        } else {
            var u = users[userId];
            if (!u) return;

            if (titleEl) titleEl.textContent = u.name + ' bearbeiten';

            // Login-Formular auf PATCH (bearbeiten)
            _setField('fieldName',     u.name);
            _setField('fieldEmail',    u.email);
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = false;
            loginMethod.value  = 'PATCH';
            loginForm.action   = (routes.update || '') + '/' + userId;

            // Rights-Formular auf PATCH (bearbeiten)
            if (rightsMethod) rightsMethod.value = 'PATCH';
            rightsForm.action = (routes.update || '') + '/' + userId;

            // Rights-Tab aktivieren
            _setRightsTabEnabled(true);
            _applyRights(u);
        }

        // Ersten Tab (Login) aktivieren
        var firstTabBtn = document.getElementById('userTab-login-btn');
        if (firstTabBtn) ckModalTab('userModal', 'userTab-login', firstTabBtn);

        ckModalOpen('userModal');
    };

    /**
     * Rights-Tab aktivieren oder deaktivieren.
     * Deaktiviert = Button ausgegraut, Klick ignoriert, Hinweis sichtbar.
     */
    function _setRightsTabEnabled(enabled) {
        if (!rightsTabBtn) return;

        if (enabled) {
            rightsTabBtn.disabled = false;
            rightsTabBtn.classList.remove('ck-modal-tab--disabled');
            if (createHint) createHint.classList.add('is-hidden');
        } else {
            rightsTabBtn.disabled = true;
            rightsTabBtn.classList.add('ck-modal-tab--disabled');
            if (createHint) createHint.classList.remove('is-hidden');
        }
    }

    /**
     * Rollen-Radio onChange – Klassen umschalten + Custom-Block ein/ausblenden.
     */
    window.usersRoleChanged = function (input) {
        document.querySelectorAll('.ck-role-option').forEach(function (el) {
            el.classList.remove('ck-role-option--selected', 'ck-role-option--selected-custom');
        });

        var label = document.getElementById('roleOption-' + input.value);
        if (label) {
            label.classList.add(input.value === 'custom'
                ? 'ck-role-option--selected-custom'
                : 'ck-role-option--selected');
        }

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
        document.querySelectorAll('input[name="role"]').forEach(function (r) { r.checked = false; });
        document.querySelectorAll('input[name="permissions[]"]').forEach(function (p) { p.checked = false; });
        document.querySelectorAll('.ck-role-option').forEach(function (el) {
            el.classList.remove('ck-role-option--selected', 'ck-role-option--selected-custom');
        });

        var customBlock = document.getElementById('customPermissions');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (user.roles && user.roles.length > 0) {
            var roleInput = document.getElementById('roleRadio-' + user.roles[0]);
            if (roleInput) {
                roleInput.checked = true;
                usersRoleChanged(roleInput);
            }
        } else if (user.permissions && user.permissions.length > 0) {
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
