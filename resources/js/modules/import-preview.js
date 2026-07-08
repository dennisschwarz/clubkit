/**
 * import-preview.js
 * Controls the preview table in step 3 of the import wizard.
 *
 * Rules:
 *  - No el.style.*  → classList only
 *  - No inline styling
 *  - IIFE: all helper functions are private, window.* exports only for HTML onclick/onchange
 */
(function () {
    'use strict';

    // ── Filter tabs ───────────────────────────────────────────────────────────

    /**
     * Filters the preview table by import status.
     * Called via onclick="importFilter('new', this)" from the Blade view.
     *
     * @param {string}      status  'all' | 'new' | 'changed' | 'unchanged'
     * @param {HTMLElement} btn     Clicked tab button (receives the --active class)
     */
    function importFilter(status, btn) {
        // Tab buttons: set the active marker
        const tabs = document.querySelectorAll('#importFilterTabs .ck-local-tab');
        for (let i = 0; i < tabs.length; i++) {
            tabs[i].classList.remove('ck-local-tab--active');
        }
        btn.classList.add('ck-local-tab--active');

        // Show / hide rows
        const rows = document.querySelectorAll('#importTable tbody tr.ck-import-row');
        for (let j = 0; j < rows.length; j++) {
            const rowStatus = rows[j].dataset.status;
            if (status === 'all' || rowStatus === status) {
                rows[j].classList.remove('is-hidden');
            } else {
                rows[j].classList.add('is-hidden');
            }
        }

        importUpdateCount();
    }

    // ── Checkbox helpers ──────────────────────────────────────────────────────

    /**
     * Master checkbox: enable or disable all.
     * Called via onchange="importToggleAll(this)".
     *
     * @param {HTMLInputElement} masterCheckbox
     */
    function importToggleAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = masterCheckbox.checked;
        }
        importUpdateCount();
    }

    /** Select all checkboxes. */
    function importSelectAll() {
        const checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = true;
        }
        importUpdateCount();
    }

    /** Deselect all checkboxes. */
    function importSelectNone() {
        const checkboxes = document.querySelectorAll('.ck-import-check:not(:disabled)');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
        }
        importUpdateCount();
    }

    /** Select only new and changed rows. */
    function importSelectNewAndChanged() {
        const rows = document.querySelectorAll('#importTable tbody tr.ck-import-row');
        for (let i = 0; i < rows.length; i++) {
            const status   = rows[i].dataset.status;
            const checkbox = rows[i].querySelector('.ck-import-check');
            if (checkbox && !checkbox.disabled) {
                checkbox.checked = (status === 'new' || status === 'changed');
            }
        }
        importUpdateCount();
    }

    // ── Update counter ────────────────────────────────────────────────────────

    /**
     * Updates the submit button text and the selection counter.
     * Called on every checkbox change.
     */
    function importUpdateCount() {
        const checked = document.querySelectorAll('.ck-import-check:checked').length;
        const btn     = document.getElementById('importSubmitBtn');
        const counter = document.getElementById('importSelectedCount');

        if (btn) {
            var btnLabel = ckUi('import_btn', 'Auswahl importieren');
            btn.textContent = checked > 0 ? btnLabel + ' (' + checked + ')' : btnLabel;
        }

        if (counter) {
            var recLabel = checked === 1
                ? ckUi('import_singular', 'Datensatz ausgewählt')
                : ckUi('import_plural',   'Datensätze ausgewählt');
            counter.textContent = checked > 0 ? checked + ' ' + recLabel : '';
        }
    }

    // ── Event listeners: checkbox changes ────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        const checkboxes = document.querySelectorAll('.ck-import-check');
        for (let i = 0; i < checkboxes.length; i++) {
            checkboxes[i].addEventListener('change', importUpdateCount);
        }
        importUpdateCount();
    });

    // ── Exports (only functions called from HTML onclick/onchange) ────────────
    window.importFilter              = importFilter;
    window.importToggleAll           = importToggleAll;
    window.importSelectAll           = importSelectAll;
    window.importSelectNone          = importSelectNone;
    window.importSelectNewAndChanged = importSelectNewAndChanged;
    window.importUpdateCount         = importUpdateCount;

}());
