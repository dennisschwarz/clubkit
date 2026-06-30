/**
 * custom-fields-modal.js
 *
 * Zwei Verantwortlichkeiten:
 *  1. cfDefModal  → Feld-Definitionen anlegen/bearbeiten (Modul-Einstellungen)
 *  2. CF-Tab in Entity-Modals befüllen (Member, Team, Event, Management)
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Daten kommen aus window.CK_CustomFields (Definitionen)
 *    bzw. window.CK_Members / CK_Teams / CK_Events / CK_Management (Entity-spezifisch)
 */

(function () {
    'use strict';

    function el(id) { return document.getElementById(id); }

    // ════════════════════════════════════════════════════════════════════════
    //  1. Feld-Definitionen Modal (cfDefModal)
    // ════════════════════════════════════════════════════════════════════════

    const cfData = window.CK_CustomFields || {};

    window.cfDefModalOpen = function (mode, objectType, defId) {
        objectType = objectType || null;
        defId      = defId      || null;

        const form        = el('cfDefForm');
        const methodInput = el('cfDefMethod');
        const routes      = cfData.routes || {};

        if (!form) return;

        form.reset();
        _toggleOptionsBlock('text');

        if (mode === 'create') {
            _cfDefSetTitle('Feld anlegen');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

            if (objectType) {
                _setField('cfDefObjectType', objectType);
            }

        } else if (mode === 'edit' && defId) {
            const def = (cfData.definitions || {})[defId];
            if (!def) return;

            _cfDefSetTitle('Feld bearbeiten');
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + defId;

            _setField('cfDefObjectType',  def.object_type);
            _setField('cfDefLabel',       def.label);
            _setField('cfDefFieldType',   def.field_type);
            _setField('cfDefPlaceholder', def.placeholder || '');
            _setField('cfDefOptionsRaw',  def.options_raw || '');
            _setChecked('cfDefIsRequired', def.is_required);

            _toggleOptionsBlock(def.field_type);
        }

        ckModalOpen('cfDefModal');
    };

    function _cfDefSetTitle(text) {
        const t = document.querySelector('#cfDefModal .ck-modal__title');
        if (t) t.textContent = text;
    }

    function _toggleOptionsBlock(fieldType) {
        const block = el('cfDefOptionsBlock');
        if (!block) return;
        if (fieldType === 'select') {
            block.classList.remove('is-hidden');
        } else {
            block.classList.add('is-hidden');
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    //  2. CF-Tab in Entity-Modals befüllen
    // ════════════════════════════════════════════════════════════════════════

    window.cfFillModal = function (formId, hintId, entityId, values, upsertUrl) {
        const form = el(formId);
        const hint = el(hintId);
        if (!form) return;

        if (!entityId) {
            form.reset();
            form.action = '';
            if (hint) hint.classList.remove('is-hidden');
            form.classList.add('is-hidden');
            return;
        }

        if (hint) hint.classList.add('is-hidden');
        form.classList.remove('is-hidden');
        form.action = upsertUrl + '/' + entityId;

        form.querySelectorAll('[data-cf-def]').forEach(function (input) {
            const defId = input.getAttribute('data-cf-def');
            const val   = (values || {})[defId];

            if (input.type === 'checkbox') {
                input.checked = (val === '1');
            } else {
                input.value = val || '';
            }
        });
    };

    // ── Event-Listener für alle unterstützten Modals ──────────────────────

    // Mitglieder
    window.ckOn('member.modal.open', function (detail) {
        const cf     = (window.CK_Members || {}).customFields || {};
        if (!cf.definitions || !cf.definitions.length) return;
        const values = detail.memberId ? ((cf.values || {})[detail.memberId] || {}) : {};
        cfFillModal('memberCfForm', 'memberCfCreateHint', detail.memberId, values, cf.upsertRoute || '');
    });

    // Teams
    window.ckOn('team.modal.open', function (detail) {
        const cf     = (window.CK_Teams || {}).customFields || {};
        if (!cf.definitions || !cf.definitions.length) return;
        const values = detail.teamId ? ((cf.values || {})[detail.teamId] || {}) : {};
        cfFillModal('teamCfForm', 'teamCfCreateHint', detail.teamId, values, cf.upsertRoute || '');
    });

    // Termine
    window.ckOn('event.modal.open', function (detail) {
        const cf     = (window.CK_Events || {}).customFields || {};
        if (!cf.definitions || !cf.definitions.length) return;
        const values = detail.eventId ? ((cf.values || {})[detail.eventId] || {}) : {};
        cfFillModal('evtCfForm', 'evtCfCreateHint', detail.eventId, values, cf.upsertRoute || '');
    });

    // Management: Funktionen
    window.ckOn('management.function.modal.open', function (detail) {
        const cf     = (window.CK_Management || {}).customFieldsFunction || {};
        if (!cf.definitions || !cf.definitions.length) return;
        const values = detail.functionId ? ((cf.values || {})[detail.functionId] || {}) : {};
        cfFillModal('mgmtFunctionCfForm', 'mgmtFunctionCfCreateHint', detail.functionId, values, cf.upsertRoute || '');
    });

    // Management: Aufgaben
    window.ckOn('management.task.modal.open', function (detail) {
        const cf     = (window.CK_Management || {}).customFieldsTask || {};
        if (!cf.definitions || !cf.definitions.length) return;
        const values = detail.taskId ? ((cf.values || {})[detail.taskId] || {}) : {};
        cfFillModal('mgmtTaskCfForm', 'mgmtTaskCfCreateHint', detail.taskId, values, cf.upsertRoute || '');
    });

    // ── Helpers ───────────────────────────────────────────────────────────

    function _setField(id, value) {
        const input = el(id);
        if (input) input.value = value;
    }

    function _setChecked(id, checked) {
        const input = el(id);
        if (input) input.checked = !!checked;
    }

    // ── Initialisierung ───────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        const typeSelect = el('cfDefFieldType');
        if (typeSelect) {
            typeSelect.addEventListener('change', function () {
                _toggleOptionsBlock(this.value);
            });
        }
    });

}());
