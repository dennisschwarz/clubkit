/**
 * ClubKit Teams – Modal Logic
 * Erwartet window.CK_Teams (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 */
(function () {
    'use strict';

    var cfg    = window.CK_Teams || {};
    var teams  = cfg.teams  || {};
    var routes = cfg.routes || {};

    var form        = document.getElementById('teamForm');
    var methodInput = document.getElementById('teamFormMethod');
    var titleEl     = document.getElementById('teamModal-title');

    window.teamsModalOpen = function (mode, teamId) {
        teamId = teamId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neues Team anlegen';
            _setField('tFieldName',     '');
            _setField('tFieldSeason',   '');
            _setField('tFieldLeague',   '');
            _setField('tFieldAgeClass', '');
            _setChecked('tFieldActive', true);
            methodInput.value = 'POST';
            form.action       = routes.store || '';
        } else {
            var t = teams[teamId];
            if (!t) return;
            if (titleEl) titleEl.textContent = t.name + ' bearbeiten';
            _setField('tFieldName',     t.name);
            _setField('tFieldSeason',   t.season    || '');
            _setField('tFieldLeague',   t.league    || '');
            _setField('tFieldAgeClass', t.age_class || '');
            _setChecked('tFieldActive', t.is_active);
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + teamId;
        }

        ckModalOpen('teamModal');
    };

    function _setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function _setChecked(id, checked) {
        var el = document.getElementById(id);
        if (el) el.checked = !!checked;
    }

}());
