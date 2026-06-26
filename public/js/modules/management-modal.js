/**
 * management-modal.js
 * Steuert Funktions- und Aufgaben-Modals im Management-Modul.
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Keine Arrow-Functions in @json() Blade-Blöcken
 *  - Daten kommen aus window.CK_Management
 *
 * Emittierte Ereignisse:
 *   ck:management.function.modal.open  → { mode, functionId, fn }
 *   ck:management.task.modal.open      → { mode, taskId, task }
 */

(function () {
    'use strict';

    var data = window.CK_Management || {};

    function el(id) { return document.getElementById(id); }

    function resetForm(formId, methodId, defaultAction) {
        var form = el(formId);
        if (!form) return;
        form.reset();
        form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.checked = false;
        });
        if (el(methodId)) el(methodId).value = 'POST';
        form.action = defaultAction;
    }

    function setCheckboxes(listId, selectedIds) {
        var list = el(listId);
        if (!list) return;
        list.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
            cb.checked = selectedIds.indexOf(parseInt(cb.value, 10)) !== -1;
        });
    }

    function setTitle(modalId, text) {
        var titleEl = document.querySelector('#' + modalId + ' .ck-modal__title');
        if (titleEl) titleEl.textContent = text;
    }

    function resetModalTabs(modalId, firstTabId, firstSectionId) {
        var modal = el(modalId);
        if (!modal) return;
        modal.querySelectorAll('.ck-modal__section').forEach(function (s) {
            s.classList.remove('ck-modal__section--active');
        });
        modal.querySelectorAll('.ck-modal-tab').forEach(function (b) {
            b.classList.remove('ck-modal-tab--active');
        });
        var firstTab     = el(firstTabId);
        var firstSection = el(firstSectionId);
        if (firstTab)     firstTab.classList.add('ck-modal-tab--active');
        if (firstSection) firstSection.classList.add('ck-modal__section--active');
    }

    // ── Einheitlicher Öffner ─────────────────────────────────────────────────
    window.mgmtModalOpen = function (type, mode, id) {
        if (type === 'function') {
            _openFunctionModal(mode, id || null);
        } else if (type === 'task') {
            _openTaskModal(mode, id || null);
        }
    };

    // ── Funktions-Modal ───────────────────────────────────────────────────────

    function _openFunctionModal(mode, functionId) {
        var routes = data.routes || {};
        resetForm('mgmtFunctionForm', 'mgmtFunctionFormMethod', routes.functionStore || '');
        resetModalTabs('mgmtFunctionModal', null, 'mgmtFunctionTab-form');

        // Ersten Tab aktiv setzen
        var firstTab = document.querySelector('#mgmtFunctionModal .ck-modal-tab');
        if (firstTab) firstTab.classList.add('ck-modal-tab--active');

        if (mode === 'create') {
            setTitle('mgmtFunctionModal', 'Neue Funktion anlegen');

            ckModalOpen('mgmtFunctionModal');
            ckEmit('management.function.modal.open', {
                mode: 'create', functionId: null, fn: null
            });

        } else if (mode === 'edit' && functionId) {
            var fn = (data.functions || {})[functionId];
            if (!fn) return;

            setTitle('mgmtFunctionModal', '„' + fn.name + '" bearbeiten');
            if (el('mgmtFunctionFormMethod')) el('mgmtFunctionFormMethod').value = 'PATCH';
            if (el('mgmtFunctionForm'))       el('mgmtFunctionForm').action      = (routes.functionUpdate || '') + '/' + fn.id;
            if (el('mgmtFunctionFieldName'))  el('mgmtFunctionFieldName').value  = fn.name;

            setCheckboxes('mgmtFunctionTeamList',   fn.team_ids   || []);
            setCheckboxes('mgmtFunctionMemberList', fn.member_ids || []);

            ckModalOpen('mgmtFunctionModal');
            ckEmit('management.function.modal.open', {
                mode: 'edit', functionId: functionId, fn: fn
            });
        }
    }

    // ── Aufgaben-Modal ─────────────────────────────────────────────────────────

    function _openTaskModal(mode, taskId) {
        var routes = data.routes || {};
        resetForm('mgmtTaskForm', 'mgmtTaskFormMethod', routes.taskStore || '');
        resetModalTabs('mgmtTaskModal', null, 'mgmtTaskTab-form');

        var firstTab = document.querySelector('#mgmtTaskModal .ck-modal-tab');
        if (firstTab) firstTab.classList.add('ck-modal-tab--active');

        if (mode === 'create') {
            setTitle('mgmtTaskModal', 'Neue Aufgabe anlegen');

            ckModalOpen('mgmtTaskModal');
            ckEmit('management.task.modal.open', {
                mode: 'create', taskId: null, task: null
            });

        } else if (mode === 'edit' && taskId) {
            var task = (data.tasks || {})[taskId];
            if (!task) return;

            setTitle('mgmtTaskModal', '„' + task.name + '" bearbeiten');
            if (el('mgmtTaskFormMethod')) el('mgmtTaskFormMethod').value = 'PATCH';
            if (el('mgmtTaskForm'))       el('mgmtTaskForm').action      = (routes.taskUpdate || '') + '/' + task.id;
            if (el('mgmtTaskFieldName'))  el('mgmtTaskFieldName').value  = task.name;
            if (el('mgmtTaskFieldDesc'))  el('mgmtTaskFieldDesc').value  = task.description || '';

            setCheckboxes('mgmtTaskTeamList',   task.team_ids   || []);
            setCheckboxes('mgmtTaskMemberList', task.member_ids || []);

            ckModalOpen('mgmtTaskModal');
            ckEmit('management.task.modal.open', {
                mode: 'edit', taskId: taskId, task: task
            });
        }
    }

    // ── Suchfeld für Mitgliederlisten ──────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        _addMemberSearch('mgmtFunctionMemberList');
        _addMemberSearch('mgmtTaskMemberList');
    });

    function _addMemberSearch(listId) {
        var memberList = el(listId);
        if (!memberList) return;

        var searchWrap  = document.createElement('div');
        searchWrap.className = 'ck-multiselect-search';

        var searchInput = document.createElement('input');
        searchInput.type        = 'text';
        searchInput.placeholder = 'Mitglied suchen…';
        searchInput.className   = 'ck-field__input ck-field__input--sm';

        searchWrap.appendChild(searchInput);
        memberList.parentNode.insertBefore(searchWrap, memberList);

        searchInput.addEventListener('input', function () {
            var term = this.value.toLowerCase();
            memberList.querySelectorAll('.ck-multiselect-item').forEach(function (item) {
                var text = item.querySelector('.ck-multiselect-item__label').textContent.toLowerCase();
                if (text.indexOf(term) !== -1) {
                    item.classList.remove('is-hidden');
                } else {
                    item.classList.add('is-hidden');
                }
            });
        });
    }

}());
