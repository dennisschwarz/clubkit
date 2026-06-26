/**
 * ClubKit Users – Modal Logic
 * Erwartet window.CK_Users (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 */
(function () {
    'use strict';

    var cfg    = window.CK_Users || {};
    var users  = cfg.users  || {};
    var roles  = cfg.roles  || {};
    var routes = cfg.routes || {};

    var loginForm    = document.getElementById('userLoginForm');
    var rightsForm   = document.getElementById('userRightsForm');
    var loginMethod  = document.getElementById('userLoginMethod');
    var rightsMethod = document.getElementById('userRightsMethod');
    var titleEl      = document.getElementById('userModal-title');

    var rightsTabBtn  = document.getElementById('userTab-rights-btn');
    var createHint    = document.getElementById('userRightsCreateHint');
    var roleSelect    = document.getElementById('roleSelect');
    var permPreview   = document.getElementById('rolePermPreview');
    var permList      = document.getElementById('rolePermList');
    var saHint        = document.getElementById('superAdminHint');
    var customBlock   = document.getElementById('customPermissions');

    // ── Modal öffnen ─────────────────────────────────────────────────────────

    window.usersModalOpen = function (mode, userId) {
        userId = userId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neuen Nutzer anlegen';
            _setField('fieldName',     '');
            _setField('fieldEmail',    '');
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = true;
            loginMethod.value = 'POST';
            loginForm.action  = routes.store || '';
            _setRightsTabEnabled(false);

        } else {
            var u = users[userId];
            if (!u) return;

            if (titleEl) titleEl.textContent = u.name + ' bearbeiten';
            _setField('fieldName',     u.name);
            _setField('fieldEmail',    u.email);
            _setField('fieldPassword', '');
            document.getElementById('fieldPassword').required = false;
            loginMethod.value  = 'PATCH';
            loginForm.action   = (routes.update || '') + '/' + userId;
            rightsMethod.value = 'PATCH';
            rightsForm.action  = (routes.update || '') + '/' + userId;

            _setRightsTabEnabled(true);
            _applyRights(u);
        }

        // Ersten Tab aktivieren
        var firstTabBtn = document.getElementById('userTab-login-btn');
        if (firstTabBtn) ckModalTab('userModal', 'userTab-login', firstTabBtn);
        ckModalOpen('userModal');
    };

    // ── Dropdown onChange ─────────────────────────────────────────────────────

    window.usersRoleChanged = function (value) {
        // Permissions-Vorschau + Super-Admin-Hinweis zurücksetzen
        if (permPreview) permPreview.classList.add('is-hidden');
        if (saHint)      saHint.classList.add('is-hidden');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (!value || value === '') {
            return;
        }

        if (value === 'custom') {
            // Benutzerdefiniert: Checkboxen zeigen
            if (customBlock) customBlock.classList.remove('is-hidden');
            return;
        }

        var roleData = roles[value];
        if (!roleData) return;

        // Super-Admin: eigener Hinweis
        if (value === 'super-admin') {
            if (saHint) saHint.classList.remove('is-hidden');
            return;
        }

        // Permissions der Rolle anzeigen
        if (permList && roleData.permissions) {
            permList.innerHTML = '';

            if (roleData.permissions.length === 0) {
                permList.innerHTML = '<span class="ck-text-muted">Keine Permissions zugewiesen</span>';
            } else {
                // Gruppieren nach Modul-Präfix
                var grouped = {};
                for (var i = 0; i < roleData.permissions.length; i++) {
                    var perm   = roleData.permissions[i];
                    var module = perm.split('.')[0];
                    if (!grouped[module]) grouped[module] = [];
                    grouped[module].push(perm);
                }

                // Ausgabe pro Modul
                for (var mod in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, mod)) continue;
                    var groupEl = document.createElement('div');
                    groupEl.className = 'ck-role-perm-preview__group';

                    var labelEl = document.createElement('span');
                    labelEl.className = 'ck-role-perm-preview__module';
                    labelEl.textContent = mod;
                    groupEl.appendChild(labelEl);

                    for (var j = 0; j < grouped[mod].length; j++) {
                        var chip = document.createElement('span');
                        chip.className   = 'ck-role-perm-preview__chip';
                        chip.textContent = grouped[mod][j];
                        groupEl.appendChild(chip);
                    }

                    permList.appendChild(groupEl);
                }
            }

            if (permPreview) permPreview.classList.remove('is-hidden');
        }
    };

    // ── Rights-Tab aktivieren/deaktivieren ────────────────────────────────────

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

    // ── Rechte eines Users eintragen ──────────────────────────────────────────

    function _applyRights(user) {
        // Dropdown zurücksetzen
        if (roleSelect) roleSelect.value = '';

        // Custom-Checkboxen zurücksetzen
        var checkboxes = document.querySelectorAll('input[name="permissions[]"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }

        // Vorschau-Panel zurücksetzen
        if (permPreview) permPreview.classList.add('is-hidden');
        if (saHint)      saHint.classList.add('is-hidden');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (user.roles && user.roles.length > 0) {
            // Rolle im Dropdown setzen + Vorschau anzeigen
            var roleName = user.roles[0];
            if (roleSelect) roleSelect.value = roleName;
            usersRoleChanged(roleName);

        } else if (user.permissions && user.permissions.length > 0) {
            // Benutzerdefiniert
            if (roleSelect) roleSelect.value = 'custom';
            if (customBlock) customBlock.classList.remove('is-hidden');

            for (var j = 0; j < user.permissions.length; j++) {
                var safeKey = user.permissions[j].replace(/[.\-]/g, '_');
                var cb = document.getElementById('custPerm_' + safeKey);
                if (cb) cb.checked = true;
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

}());
