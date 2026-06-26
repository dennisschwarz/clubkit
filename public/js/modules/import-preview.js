/**
 * import-preview.js
 * Steuerung der Vorschau-Tabelle in Stufe 3 des Import-Assistenten.
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Kein inline-Styling
 */

'use strict';

// ── Filter-Tabs ───────────────────────────────────────────────────────────────

function importFilter(status, btn) {
    // Tab-Buttons: aktiven Marker setzen
    var tabs = document.querySelectorAll('#importFilterTabs .ck-local-tab');
    for (var i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove('ck-local-tab--active');
    }
    btn.classList.add('ck-local-tab--active');

    // Zeilen ein-/ausblenden
    var rows = document.querySelectorAll('#importTable tbody tr.ck-import-row');
    for (var j = 0; j < rows.length; j++) {
        var rowStatus = rows[j].dataset.status;
        if (status === 'all' || rowStatus === status) {
            rows[j].classList.remove('is-hidden');
        } else {
            rows[j].classList.add('is-hidden');
        }
    }

    importUpdateCount();
}

// ── Checkbox-Helpers ──────────────────────────────────────────────────────────

function importToggleAll(masterCheckbox) {
    var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = masterCheckbox.checked;
    }
    importUpdateCount();
}

function importSelectAll() {
    var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = true;
    }
    importUpdateCount();
}

function importSelectNone() {
    var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
    }
    importUpdateCount();
}

function importSelectNewAndChanged() {
    var rows = document.querySelectorAll('#importTable tbody tr.ck-import-row');
    for (var i = 0; i < rows.length; i++) {
        var status   = rows[i].dataset.status;
        var checkbox = rows[i].querySelector('.ck-import-check');
        if (checkbox && !checkbox.disabled) {
            checkbox.checked = (status === 'new' || status === 'changed');
        }
    }
    importUpdateCount();
}

// ── Zähler aktualisieren ──────────────────────────────────────────────────────

function importUpdateCount() {
    var checked = document.querySelectorAll('.ck-import-check:checked').length;
    var btn     = document.getElementById('importSubmitBtn');
    var counter = document.getElementById('importSelectedCount');

    if (btn) {
        btn.textContent = checked > 0
            ? 'Auswahl importieren (' + checked + ')'
            : 'Auswahl importieren';
    }

    if (counter) {
        counter.textContent = checked > 0
            ? checked + ' Datensatz' + (checked !== 1 ? 'e' : '') + ' ausgewählt'
            : '';
    }
}

// ── Event-Listener: Checkbox-Änderungen ──────────────────────────────────────

document.addEventListener('DOMContentLoaded', function () {
    var checkboxes = document.querySelectorAll('.ck-import-check');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].addEventListener('change', importUpdateCount);
    }
    importUpdateCount();
});
