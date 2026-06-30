/**
 * ClubKit Users – Modal Logic
 * Expects window.CK_Users (data bridge from Blade view).
 * Rule: ONLY classList operations, no el.style.*
 */
(function () {
    'use strict';

    const cfg    = window.CK_Users || {};
    const users  = cfg.users  || {};
    const roles  = cfg.roles  || {};
    const routes = cfg.routes || {};

    const loginForm    = document.getElementById('userLoginForm');
    const rightsForm   = document.getElementById('userRightsForm');
    const loginMethod  = document.getElementById('userLoginMethod');
    const rightsMethod = document.getElementById('userRightsMethod');
    const titleEl      = document.getElementById('userModal-title');

    const rightsTabBtn = document.getElementById('userTab-rights-btn');
    const createHint   = document.getElementById('userRightsCreateHint');
    const roleSelect   = document.getElementById('roleSelect');
    const permPreview  = document.getElementById('rolePermPreview');
    const permList     = document.getElementById('rolePermList');
    const saHint       = document.getElementById('superAdminHint');
    const customBlock  = document.getElementById('customPermissions');

    // ── Open modal ────────────────────────────────────────────────────────────

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
            const u = users[userId];
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

        // Activate first tab
        const firstTabBtn = document.getElementById('userTab-login-btn');
        if (firstTabBtn) ckModalTab('userModal', 'userTab-login', firstTabBtn);
        ckModalOpen('userModal');
    };

    // ── Dropdown onChange ─────────────────────────────────────────────────────

    window.usersRoleChanged = function (value) {
        // Reset permissions preview + super-admin hint
        if (permPreview) permPreview.classList.add('is-hidden');
        if (saHint)      saHint.classList.add('is-hidden');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (!value || value === '') {
            return;
        }

        if (value === 'custom') {
            // Custom: show permission checkboxes
            if (customBlock) customBlock.classList.remove('is-hidden');
            return;
        }

        const roleData = roles[value];
        if (!roleData) return;

        // Super-admin: show dedicated hint
        if (value === 'super-admin') {
            if (saHint) saHint.classList.remove('is-hidden');
            return;
        }

        // Show the role's permissions
        if (permList && roleData.permissions) {
            permList.innerHTML = '';

            if (roleData.permissions.length === 0) {
                permList.innerHTML = '<span class="ck-text-muted">Keine Permissions zugewiesen</span>';
            } else {
                // Group by module prefix
                const grouped = {};
                for (let i = 0; i < roleData.permissions.length; i++) {
                    const perm = roleData.permissions[i];
                    const mod  = perm.split('.')[0];
                    if (!grouped[mod]) grouped[mod] = [];
                    grouped[mod].push(perm);
                }

                // Render per module
                for (const mod in grouped) {
                    if (!Object.prototype.hasOwnProperty.call(grouped, mod)) continue;
                    const groupEl = document.createElement('div');
                    groupEl.className = 'ck-role-perm-preview__group';

                    const labelEl = document.createElement('span');
                    labelEl.className   = 'ck-role-perm-preview__module';
                    labelEl.textContent = mod;
                    groupEl.appendChild(labelEl);

                    for (let j = 0; j < grouped[mod].length; j++) {
                        const chip = document.createElement('span');
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

    // ── Enable / disable rights tab ───────────────────────────────────────────

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

    // ── Apply a user's existing rights ────────────────────────────────────────

    function _applyRights(user) {
        // Reset dropdown
        if (roleSelect) roleSelect.value = '';

        // Reset custom checkboxes
        const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }

        // Reset preview panels
        if (permPreview) permPreview.classList.add('is-hidden');
        if (saHint)      saHint.classList.add('is-hidden');
        if (customBlock) customBlock.classList.add('is-hidden');

        if (user.roles && user.roles.length > 0) {
            // Set role in dropdown and show permissions preview
            const roleName = user.roles[0];
            if (roleSelect) roleSelect.value = roleName;
            usersRoleChanged(roleName);

        } else if (user.permissions && user.permissions.length > 0) {
            // Custom permissions
            if (roleSelect) roleSelect.value = 'custom';
            if (customBlock) customBlock.classList.remove('is-hidden');

            for (let j = 0; j < user.permissions.length; j++) {
                const safeKey = user.permissions[j].replace(/[.\-]/g, '_');
                const cb = document.getElementById('custPerm_' + safeKey);
                if (cb) cb.checked = true;
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _setField(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

}());
