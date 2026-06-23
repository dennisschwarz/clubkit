/**
 * ClubKit Members – Modal Logic
 * Erwartet window.CK_Members (Data Bridge aus Blade-View).
 * Regel: NUR classList-Operationen, keine el.style.*
 */
(function () {
    'use strict';

    var cfg     = window.CK_Members || {};
    var members = cfg.members || {};
    var routes  = cfg.routes  || {};

    var form        = document.getElementById('memberForm');
    var methodInput = document.getElementById('memberFormMethod');
    var titleEl     = document.getElementById('memberModal-title');

    /**
     * Modal öffnen und mit Daten befüllen
     * @param {string}     mode     – 'create' | 'edit'
     * @param {number|null} memberId
     */
    window.membersModalOpen = function (mode, memberId) {
        memberId = memberId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Neues Mitglied anlegen';
            _setField('mFieldFirstName', '');
            _setField('mFieldLastName',  '');
            _setField('mFieldGender',    '');
            _setField('mFieldDob',       '');
            _setField('mFieldStatus',    'active');
            _setChecked('mFieldEligible', false);
            methodInput.value = 'POST';
            form.action       = routes.store || '';
        } else {
            var m = members[memberId];
            if (!m) return;
            if (titleEl) titleEl.textContent = m.last_name + ', ' + m.first_name + ' bearbeiten';
            _setField('mFieldFirstName', m.first_name);
            _setField('mFieldLastName',  m.last_name);
            _setField('mFieldGender',    m.gender);
            _setField('mFieldDob',       m.date_of_birth);
            _setField('mFieldStatus',    m.status);
            _setChecked('mFieldEligible', m.eligible_to_play);
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + memberId;
        }

        ckModalOpen('memberModal');
    };

    // ── Private Helpers ──────────────────────────────────────────────────────

    function _setField(id, value) {
        var el = document.getElementById(id);
        if (el) el.value = value;
    }

    function _setChecked(id, checked) {
        var el = document.getElementById(id);
        if (el) el.checked = !!checked;
    }

}());
