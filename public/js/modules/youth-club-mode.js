/**
 * ClubKit YouthClubMode – Guardian Tab Logic
 *
 * Dieses Modul erweitert das Member-Modal um den Guardians-Tab.
 * Es weiß nichts vom Members-Modal direkt – es lauscht nur auf Ereignisse.
 *
 * Abhängigkeiten:
 *   - window.CK_Members (aus members::index Data Bridge, durch View Composer angereichert)
 *   - ckOn() / ckTabEnable() aus resources/js/app.js
 *   - members-modal.js muss vor dieser Datei geladen werden
 *
 * Gelauschte Ereignisse:
 *   ck:member.modal.open → { mode, memberId, member }
 *
 * Regel: Nur classList-Operationen – kein el.style.*
 */
(function () {
    'use strict';

    // Guardian-Daten sind durch den YouthClubMode View Composer
    // in window.CK_Members.members eingepflegt worden.
    var members = (window.CK_Members || {}).members || {};

    /**
     * Auf Modal-Öffnen lauschen.
     * members-modal.js emittiert dieses Ereignis nach jeder Initialisierung.
     */
    ckOn('member.modal.open', function (detail) {
        var mode     = detail.mode;
        var memberId = detail.memberId;
        var m        = detail.member;

        if (mode === 'create') {
            // Guardian-Tab deaktivieren – Mitglied muss erst existieren
            ckTabEnable('memberGuardianTabBtn', 'memberGuardianCreateHint', false);
            return;
        }

        // Edit-Modus: Guardian-Tab aktivieren und Felder befüllen
        ckTabEnable('memberGuardianTabBtn', 'memberGuardianCreateHint', true);

        // Guardian-Formular action setzen
        var form = document.getElementById('memberGuardianForm');
        if (form && memberId) {
            // Route: PATCH /members/{id}/parents
            form.action = '/members/' + memberId + '/parents';
        }

        // Dropdowns befüllen (father_id / mother_id aus CK_Members, angereichert vom View Composer)
        if (m) {
            _setSelect('mFieldFatherId', m.father_id);
            _setSelect('mFieldMotherId', m.mother_id);
        }
    });

    // ── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Select-Element auf einen bestimmten Wert setzen.
     * Wenn kein passender Wert gefunden wird, wird der erste Eintrag gewählt.
     */
    function _setSelect(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        var target = value !== null && value !== undefined ? String(value) : '';
        for (var i = 0; i < el.options.length; i++) {
            if (String(el.options[i].value) === target) {
                el.selectedIndex = i;
                return;
            }
        }
        el.selectedIndex = 0;
    }

}());
