/**
 * events-modal.js
 * Steuert Termin-Modal (Anlegen / Bearbeiten).
 *
 * 3-Ebenen-Modell:
 *  1. Vereinsfunktionen  → Checkboxen (nur mit Management-Modul)
 *  2. Aufgaben           → Checkboxen (nur mit Management-Modul)
 *  3. Einmalige Assignments → Person + Beschreibung (immer)
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Daten kommen aus window.CK_Events (Data Bridge in Blade)
 */

(function () {
    'use strict';

    var data = window.CK_Events || {};

    // Zähler für eindeutige Assignment-Zeilen-Indizes
    var _assignCounter = 0;

    // Flatpickr-Instanzen (werden in DOMContentLoaded initialisiert)
    var fpStart = null;
    var fpEnd   = null;

    function el(id) { return document.getElementById(id); }

    // ── Modal öffnen ──────────────────────────────────────────────────────────

    window.evtModalOpen = function (mode, eventId) {
        eventId = eventId || null;
        var form        = el('evtForm');
        var methodInput = el('evtFormMethod');
        var routes      = data.routes || {};

        if (!form) return;

        // Formular vollständig zurücksetzen
        form.reset();
        _clearAssignments();
        _clearCheckboxGroup('evtMgmtFn');
        _clearCheckboxGroup('evtTask');
        _clearCheckboxGroup('evtTeam');

        // Tab zurück auf "Termin-Daten"
        var firstTab = el('evtDatenTabBtn');
        if (firstTab) ckModalTab('evtModal', 'evtTab-daten', firstTab);

        if (mode === 'create') {
            _setTitle('Termin anlegen');
            methodInput.value = 'POST';
            form.action       = routes.store || '';

            // Datums-Picker zurücksetzen
            if (fpStart) fpStart.clear();
            if (fpEnd)   fpEnd.clear();

            ckModalOpen('evtModal');
            ckEmit('event.modal.open', { mode: 'create', eventId: null, event: null });

        } else if (mode === 'edit' && eventId) {
            var ev = (data.events || {})[eventId];
            if (!ev) return;

            _setTitle('\u201E' + ev.title + '\u201C bearbeiten');
            methodInput.value = 'PATCH';
            form.action       = (routes.update || '') + '/' + eventId;

            // Basisdaten setzen
            _setField('evtTitle',       ev.title       || '');
            // Datum via Flatpickr setzen (setzt hidden input + altInput)
            if (fpStart) fpStart.setDate(ev.starts_at || '');
            else         _setField('evtStartsAt', ev.starts_at || '');
            if (fpEnd)   fpEnd.setDate(ev.ends_at   || '');
            else         _setField('evtEndsAt',   ev.ends_at   || '');
            _setField('evtLocation',    ev.location    || '');
            _setField('evtDescription', ev.description || '');
            _setField('evtNotes',       ev.notes       || '');

            // Sektion 1: Vereinsfunktionen-Checkboxen
            var fnIds = ev.management_function_ids || [];
            for (var i = 0; i < fnIds.length; i++) {
                _setCheckbox('evtMgmtFn' + fnIds[i], true);
            }

            // Sektion 2: Aufgaben-Checkboxen
            var taskIds = ev.task_ids || [];
            for (var j = 0; j < taskIds.length; j++) {
                _setCheckbox('evtTask' + taskIds[j], true);
            }

            // Sektion 3: Einmalige Assignments
            var assignments = ev.assignments || [];
            for (var k = 0; k < assignments.length; k++) {
                _addAssignmentRow(
                    assignments[k].member_id,
                    assignments[k].description || ''
                );
            }

            // Teams
            var teamIds = ev.team_ids || [];
            for (var l = 0; l < teamIds.length; l++) {
                _setCheckbox('evtTeam' + teamIds[l], true);
            }

            ckModalOpen('evtModal');
            ckEmit('event.modal.open', { mode: 'edit', eventId: eventId, event: ev });
        }
    };

    // ── Assignment-Zeile hinzufügen ───────────────────────────────────────────

    window.evtAddAssignment = function () {
        _addAssignmentRow('', '');
    };

    function _addAssignmentRow(memberId, description) {
        var list = el('evtAssignmentList');
        if (!list) return;

        var idx     = _assignCounter++;
        var members = data.members || {};

        var options = '<option value="">Person ausw\u00E4hlen\u2026</option>';
        for (var id in members) {
            if (!Object.prototype.hasOwnProperty.call(members, id)) continue;
            var selected = (String(id) === String(memberId)) ? ' selected' : '';
            options += '<option value="' + id + '"' + selected + '>'
                     + _esc(members[id].name) + '</option>';
        }

        var row = document.createElement('div');
        row.className   = 'ck-organizer-row';
        row.dataset.idx = idx;
        row.innerHTML   =
            '<select name="assignments[' + idx + '][member_id]" class="ck-field__input">'
            + options
            + '</select>'
            + '<input type="text"'
            +        ' name="assignments[' + idx + '][description]"'
            +        ' value="' + _esc(description) + '"'
            +        ' placeholder="z.B. Schiedsrichter, Catering"'
            +        ' class="ck-field__input">'
            + '<button type="button" class="ck-btn ck-btn--danger ck-btn--icon"'
            +         ' onclick="evtRemoveAssignment(this)" title="Entfernen">'
            + '\u00D7'
            + '</button>';

        list.appendChild(row);
    }

    window.evtRemoveAssignment = function (btn) {
        var row = btn.closest('.ck-organizer-row');
        if (row) row.remove();
    };

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _setField(id, value) {
        var input = el(id);
        if (input) input.value = value;
    }

    function _setCheckbox(id, checked) {
        var input = el(id);
        if (input) input.checked = !!checked;
    }

    function _setTitle(text) {
        var t = document.querySelector('#evtModal .ck-modal__title');
        if (t) t.textContent = text;
    }

    function _clearAssignments() {
        var list = el('evtAssignmentList');
        if (list) list.innerHTML = '';
        _assignCounter = 0;
    }

    function _clearCheckboxGroup(prefix) {
        var checkboxes = document.querySelectorAll(
            'input[type="checkbox"][id^="' + prefix + '"]'
        );
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
    }

    function _esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ── Flatpickr: "Übernehmen"-Button manuell einfügen ──────────────────────
    // Kein Plugin-Import nötig – wir nutzen die onReady-Callback-API direkt.
    // Der Button bekommt nur eine CSS-Klasse (kein el.style.*).

    function _addConfirmButton(fpInstance) {
        var btn = document.createElement('div');
        btn.className   = 'ck-fp-confirm';
        btn.textContent = '\u00DCbernehmen \u2713'; // "Übernehmen ✓"
        btn.addEventListener('click', function () {
            fpInstance.close();
        });
        fpInstance.calendarContainer.appendChild(btn);
    }

    // ── Flatpickr initialisieren ──────────────────────────────────────────────
    // window.flatpickr kommt aus app.js (Vite-Bundle) mit German-Locale + Defaults.

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.flatpickr !== 'function') return;

        var startEl = document.getElementById('evtStartsAt');
        var endEl   = document.getElementById('evtEndsAt');

        if (startEl) {
            fpStart = window.flatpickr(startEl, {
                onReady: function (sel, str, instance) {
                    _addConfirmButton(instance);
                },
                onChange: function (selectedDates) {
                    // Endzeit-Minimum auf gewählten Startzeitpunkt setzen
                    if (fpEnd && selectedDates.length) {
                        fpEnd.set('minDate', selectedDates[0]);
                    }
                }
            });
        }

        if (endEl) {
            fpEnd = window.flatpickr(endEl, {
                onReady: function (sel, str, instance) {
                    _addConfirmButton(instance);
                }
            });
        }
    });

}());
