/**
 * ClubKit Members – Modal Logic
 * Erwartet window.CK_Members (gesetzt vom Blade-Data-Bridge-Script)
 */
(function () {
    'use strict';

    var data   = window.CK_Members || {};
    var routes = data.routes || {};
    var members = data.members || {};

    // ── DOM-Refs ────────────────────────────────────────────────────────────
    var modal      = document.getElementById('memberModal');
    var titleEl    = document.getElementById('memberModalTitle');
    var form       = document.getElementById('memberForm');
    var methodInput= document.getElementById('memberFormMethod');

    var fFirstName = document.getElementById('mFieldFirstName');
    var fLastName  = document.getElementById('mFieldLastName');
    var fGender    = document.getElementById('mFieldGender');
    var fDob       = document.getElementById('mFieldDob');
    var fStatus    = document.getElementById('mFieldStatus');
    var fEligible  = document.getElementById('mFieldEligible');

    // ── Öffnen ──────────────────────────────────────────────────────────────
    window.openMemberModal = function (mode, memberId) {
        if (mode === 'create') {
            titleEl.textContent     = 'Neues Mitglied anlegen';
            fFirstName.value        = '';
            fLastName.value         = '';
            fGender.value           = '';
            fDob.value              = '';
            fStatus.value           = 'active';
            fEligible.checked       = false;
            methodInput.value       = 'POST';
            form.action             = routes.store || '';
        } else {
            var m = members[memberId];
            if (!m) return;
            titleEl.textContent = m.last_name + ', ' + m.first_name + ' bearbeiten';
            fFirstName.value    = m.first_name;
            fLastName.value     = m.last_name;
            fGender.value       = m.gender;
            fDob.value          = m.date_of_birth;
            fStatus.value       = m.status;
            fEligible.checked   = m.eligible_to_play;
            methodInput.value   = 'PATCH';
            form.action         = (routes.update || '') + '/' + memberId;
        }

        modal.style.display     = 'flex';
        document.body.style.overflow = 'hidden';
    };

    // ── Schließen ───────────────────────────────────────────────────────────
    window.closeMemberModal = function (e) {
        if (e && e.target !== modal) return;
        modal.style.display          = 'none';
        document.body.style.overflow = '';
    };

    // ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            modal.style.display          = 'none';
            document.body.style.overflow = '';
        }
    });

}());
