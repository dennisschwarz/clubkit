/**
 * ClubKit Members – Modal Logic
 *
 * Expects window.CK_Members (data bridge from the Blade view).
 * Rule: ONLY classList operations – no el.style.*
 *
 * This module has no knowledge of YouthClubMode or other extensions.
 * It communicates with other modules via ckEmit().
 *
 * Emitted events:
 *   ck:member.modal.open  → { mode: 'create'|'edit', memberId: id|null, member: {...}|null }
 */
(function () {
    'use strict';

    const cfg     = window.CK_Members || {};
    const members = cfg.members || {};
    const routes  = cfg.routes  || {};

    const form        = document.getElementById('memberForm');
    const photoForm   = document.getElementById('memberPhotoForm');
    const methodInput = document.getElementById('memberFormMethod');
    const titleEl     = document.getElementById('memberModal-title');

    /**
     * Opens and populates the member modal.
     *
     * @param {string}      mode      'create' | 'edit'
     * @param {number|null} memberId
     */
    window.membersModalOpen = function (mode, memberId) {
        memberId = memberId || null;

        if (mode === 'create') {
            if (titleEl) titleEl.textContent = ckUi('member_create', 'Mitglied hinzufügen');

            _setField('mFieldFirstName',  '');
            _setField('mFieldLastName',   '');
            _setField('mFieldPassNumber', '');
            _setField('mFieldGender',     '');
            _setDateField('mFieldDob',      '');
            _setField('mFieldStatus',     'active');
            _setDateField('mFieldEligible', '');  // date field – empty = not eligible

            methodInput.value = 'POST';
            form.action       = routes.store || '';

            // Disable photo tab – member must be saved first
            ckTabEnable('memberPhotoTabBtn', 'memberPhotoCreateHint', false);

            // Activate first tab
            const firstTab = document.querySelector('#memberModal .ck-modal-tab');
            if (firstTab) ckModalTab('memberModal', 'memberTab-stamm', firstTab);

            ckModalOpen('memberModal');

            ckEmit('member.modal.open', { mode: 'create', memberId: null, member: null });

        } else {
            const m = members[memberId];
            if (!m) return;

            if (titleEl) titleEl.textContent = m.last_name + ', ' + m.first_name + ckUi('edit_suffix', ' bearbeiten');

            _setField('mFieldFirstName',  m.first_name);
            _setField('mFieldLastName',   m.last_name);
            _setField('mFieldPassNumber', m.pass_number    || '');
            _setField('mFieldGender',     m.gender         || '');
            _setDateField('mFieldDob',      m.date_of_birth  || '');
            _setField('mFieldStatus',     m.status         || 'active');
            // eligible_to_play_date = YYYY-MM-DD or '' (date field, no checkbox)
            _setDateField('mFieldEligible', m.eligible_to_play_date || '');

            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + memberId;

            if (photoForm) {
                photoForm.action = (routes.update || '') + '/' + memberId + '/photo';
            }

            ckTabEnable('memberPhotoTabBtn', 'memberPhotoCreateHint', true);
            _resetPhotoPreview(m.profile_image || '');

            const firstTab = document.querySelector('#memberModal .ck-modal-tab');
            if (firstTab) ckModalTab('memberModal', 'memberTab-stamm', firstTab);

            ckModalOpen('memberModal');

            ckEmit('member.modal.open', { mode: 'edit', memberId: memberId, member: m });
        }
    };

    // ── Photo preview ─────────────────────────────────────────────────────────

    function _resetPhotoPreview(existingUrl) {
        const preview     = document.getElementById('photoPreview');
        const placeholder = document.getElementById('photoPreviewPlaceholder');
        const fileInput   = document.getElementById('mFieldPhoto');

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

    // Live preview on file selection
    const fileInput = document.getElementById('mFieldPhoto');
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const preview     = document.getElementById('photoPreview');
            const placeholder = document.getElementById('photoPreviewPlaceholder');
            if (!preview || !this.files || !this.files[0]) return;

            const reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.classList.remove('is-hidden');
                if (placeholder) placeholder.classList.add('is-hidden');
            };
            reader.readAsDataURL(this.files[0]);
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    function _setField(id, value) {
        const el = document.getElementById(id);
        if (el) el.value = value;
    }

    /**
     * Set a flatpickr-wrapped date field.
     *
     * Plain _setField() only updates the hidden original input; flatpickr's
     * visible altInput stays stale. This helper calls the flatpickr API so
     * both the raw value and the formatted display stay in sync.
     *
     * @param {string} id    - Element ID of the original date input.
     * @param {string} value - ISO date string ('YYYY-MM-DD') or empty string.
     */
    function _setDateField(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el._flatpickr) {
            if (value) { el._flatpickr.setDate(value, false); }
            else        { el._flatpickr.clear(); }
        } else {
            el.value = value || '';
        }
    }

}());