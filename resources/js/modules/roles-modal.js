/**
 * ClubKit Roles – Modal Logic
 * Expects window.CK_Roles (data bridge from Blade view).
 * Rule: ONLY classList operations, no el.style.*
 */
(function () {
    'use strict';

    const cfg         = window.CK_Roles || {};
    const roles       = cfg.roles       || {};
    const routes      = cfg.routes      || {};
    const systemRoles = cfg.systemRoles || [];

    const form        = document.getElementById('roleForm');
    const methodInput = document.getElementById('roleFormMethod');
    const titleEl     = document.getElementById('roleModal-title');
    const nameField   = document.getElementById('roleNameField');

    window.rolesModalOpen = function (mode, roleId) {
        roleId = roleId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = ckUi('role_create', 'Neue Rolle anlegen');
            _setField('roleFieldName', '');
            _uncheckAll();
            if (nameField) nameField.classList.remove('is-hidden');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

        } else {
            const r = roles[roleId];
            if (!r) return;
            if (titleEl) titleEl.textContent = r.name + ckUi('edit_suffix', ' bearbeiten');

            // System roles: hide name field (cannot be renamed)
            const isSystem = systemRoles.indexOf(r.name) !== -1;
            if (nameField) {
                if (isSystem) {
                    nameField.classList.add('is-hidden');
                } else {
                    nameField.classList.remove('is-hidden');
                    _setField('roleFieldName', r.name);
                }
            }

            _uncheckAll();
            // Check existing permissions
            for (let i = 0; i < r.permissions.length; i++) {
                const permKey = r.permissions[i].replace(/\./g, '_');
                const cb = document.getElementById('perm_' + permKey);
                if (cb) cb.checked = true;
            }

            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + roleId;
        }

        ckModalOpen('roleModal');
    };

    function _uncheckAll() {
        const checkboxes = form.querySelectorAll('input[type="checkbox"]');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    function _setField(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

}());
