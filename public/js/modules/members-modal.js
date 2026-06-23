/**
 * ClubKit Members – Modal Logic
 *
 * Erwartet window.CK_Members (Data Bridge aus der Blade-View).
 * Regel: Nur classList-Operationen – kein el.style.*
 *
 * Dieses Modul weiß nichts von YouthClubMode oder anderen Erweiterungen.
 * Es kommuniziert über ckEmit() mit anderen Modulen.
 *
 * Emittierte Ereignisse:
 *   ck:member.modal.open  → { mode: 'create'|'edit', memberId: id|null, member: {...}|null }
 */
(function () {
    'use strict';

    var cfg     = window.CK_Members || {};
    var members = cfg.members || {};
    var routes  = cfg.routes  || {};

    var form        = document.getElementById('memberForm');
    var photoForm   = document.getElementById('memberPhotoForm');
    var methodInput = document.getElementById('memberFormMethod');
    var titleEl     = document.getElementById('memberModal-title');

    /**
     * Modal öffnen und befüllen.
     * @param {string}      mode      'create' | 'edit'
     * @param {number|null} memberId
     */
    window.membersModalOpen = function (mode, memberId) {
        memberId = memberId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = 'Mitglied hinzufügen';

            _setField('mFieldFirstName', '');
            _setField('mFieldLastName',  '');
            _setField('mFieldGender',    '');
            _setField('mFieldDob',       '');
            _setField('mFieldStatus',    'active');
            _setChecked('mFieldEligible', false);

            methodInput.value = 'POST';
            form.action       = routes.store || '';

            // Foto-Tab deaktivieren – Mitglied muss erst existieren
            ckTabEnable('memberPhotoTabBtn', 'memberPhotoCreateHint', false);

            // Ersten Tab aktivieren
            var firstTab = document.querySelector('#memberModal .ck-modal-tab');
            if (firstTab) ckModalTab('memberModal', 'memberTab-stamm', firstTab);

            ckModalOpen('memberModal');

            // Andere Module informieren (z. B. YouthClubMode deaktiviert seinen Tab)
            ckEmit('member.modal.open', { mode: 'create', memberId: null, member: null });

        } else {
            var m = members[memberId];
            if (!m) return;

            if (titleEl) titleEl.textContent = m.last_name + ', ' + m.first_name + ' bearbeiten';

            _setField('mFieldFirstName', m.first_name);
            _setField('mFieldLastName',  m.last_name);
            _setField('mFieldGender',    m.gender        || '');
            _setField('mFieldDob',       m.date_of_birth || '');
            _setField('mFieldStatus',    m.status        || 'active');
            _setChecked('mFieldEligible', m.eligible_to_play);

            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + memberId;

            // Foto-Formular action setzen
            if (photoForm) {
                photoForm.action = (routes.update || '') + '/' + memberId + '/photo';
            }

            // Foto-Tab aktivieren
            ckTabEnable('memberPhotoTabBtn', 'memberPhotoCreateHint', true);

            // Foto-Vorschau setzen
            _resetPhotoPreview(m.profile_image || '');

            // Ersten Tab aktivieren
            var firstTab = document.querySelector('#memberModal .ck-modal-tab');
            if (firstTab) ckModalTab('memberModal', 'memberTab-stamm', firstTab);

            ckModalOpen('memberModal');

            // Andere Module informieren (z. B. YouthClubMode befüllt seinen Tab)
            ckEmit('member.modal.open', { mode: 'edit', memberId: memberId, member: m });
        }
    };

    // ── Foto-Vorschau ────────────────────────────────────────────────────────

    function _resetPhotoPreview(existingUrl) {
        var preview     = document.getElementById('photoPreview');
        var placeholder = document.getElementById('photoPreviewPlaceholder');
        var fileInput   = document.getElementById('mFieldPhoto');

        if (fileInput) fileInput.value = '';

        if (existingUrl && preview && placeholder) {
            preview.src = existingUrl;
            preview.classList.remove('is-hidden');
            placeholder.classList.add('is-hidden');
        } else if (preview && placeholder) {
            preview.src = '';
            preview.classList.add('is-hidden');
            placeholder.classList.remove('is-hidden');
        }
    }

    // Live-Vorschau beim Datei-Upload
    var fileInput = document.getElementById('mFieldPhoto');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var preview     = document.getElementById('photoPreview');
            var placeholder = document.getElementById('photoPreviewPlaceholder');
            if (!preview || !this.files || !this.files[0]) return;

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('is-hidden');
                if (placeholder) placeholder.classList.add('is-hidden');
            };
            reader.readAsDataURL(this.files[0]);
        });
    }

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
