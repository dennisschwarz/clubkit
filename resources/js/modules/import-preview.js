/**
 * import-preview.js
 * Steuerung der Vorschau-Tabelle in Stufe 3 des Import-Assistenten.
 *
 * Regeln:
 *  - Kein el.style.*  → nur classList
 *  - Kein inline-Styling
 *  - IIFE: Alle Helfer-Funktionen privat, window.*-Exporte nur für HTML-onclick/onchange
 */
(function () {
    'use strict';

    // ── Filter-Tabs ───────────────────────────────────────────────────────────

    /**
     * Filtert die Vorschau-Tabelle nach Import-Status.
     * Wird via onclick="importFilter('new', this)" aus der Blade-View aufgerufen.
     *
     * @param {string}      status  'all' | 'new' | 'changed' | 'unchanged'
     * @param {HTMLElement} btn     Geklickter Tab-Button (erhält --active Klasse)
     */
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

    // ── Checkbox-Helpers ──────────────────────────────────────────────────────

    /**
     * Master-Checkbox: alle aktivieren oder deaktivieren.
     * Wird via onchange="importToggleAll(this)" aufgerufen.
     */
    function importToggleAll(masterCheckbox) {
        var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = masterCheckbox.checked;
        }
        importUpdateCount();
    }

    /** Alle Checkboxen aktivieren. */
    function importSelectAll() {
        var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = true;
        }
        importUpdateCount();
    }

    /** Alle Checkboxen deaktivieren. */
    function importSelectNone() {
        var checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
        importUpdateCount();
    }

    /** Nur neue und geänderte Zeilen auswählen. */
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

    // ── Zähler aktualisieren ──────────────────────────────────────────────────

    /**
     * Aktualisiert den Submit-Button-Text und den Auswahl-Zähler.
     * Wird bei jeder Checkbox-Änderung aufgerufen.
     */
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

    // ── Event-Listener: Checkbox-Änderungen ──────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        var checkboxes = document.querySelectorAll('.ck-import-check');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].addEventListener('change', importUpdateCount);
        }
        importUpdateCount();
    });

    // ── Exports (nur Funktionen die aus HTML-onclick/onchange aufgerufen werden) ──
    window.importFilter              = importFilter;
    window.importToggleAll           = importToggleAll;
    window.importSelectAll           = importSelectAll;
    window.importSelectNone          = importSelectNone;
    window.importSelectNewAndChanged = importSelectNewAndChanged;
    window.importUpdateCount         = importUpdateCount;

}());
