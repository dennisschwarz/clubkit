/**
 * ClubKit Roles – Modal Logic
 * Erwartet window.CK_Roles (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 */
(function () {
    'use strict';

    var cfg         = window.CK_Roles || {};
    var roles       = cfg.roles       || {};
    var routes      = cfg.routes      || {};
    var systemRoles = cfg.systemRoles || [];

    var form        = document.getElementById('roleForm');
    var methodInput = document.getElementById('roleFormMethod');
    var titleEl     = document.getElementById('roleModal-title');
    var nameField   = document.getElementById('roleNameField');

    window.rolesModalOpen = function (mode, roleId) {
        roleId = roleId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neue Rolle anlegen';
            _setField('roleFieldName', '');
            _uncheckAll();
            if (nameField) nameField.classList.remove('is-hidden');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

        } else {
            var r = roles[roleId];
            if (!r) return;
            if (titleEl) titleEl.textContent = r.name + ' bearbeiten';

            // Systemrollen: Namensfeld ausblenden (nicht umbenennen)
            var isSystem = systemRoles.indexOf(r.name) !== -1;
            if (nameField) {
                if (isSystem) {
                    nameField.classList.add('is-hidden');
                } else {
                    nameField.classList.remove('is-hidden');
                    _setField('roleFieldName', r.name);
                }
            }

            _uncheckAll();
            // Vorhandene Permissions ankreuzen
            for (var i = 0; i < r.permissions.length; i++) {
                var permKey = r.permissions[i].replace(/\./g, '_');
                var cb = document.getElementById('perm_' + permKey);
                if (cb) cb.checked = true;
            }

            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + roleId;
        }

        ckModalOpen('roleModal');
    };

    function _uncheckAll() {
        var checkboxes = form.querySelectorAll('input[type="checkbox"]');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    function _setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

}());
