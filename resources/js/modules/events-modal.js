/**
 * ClubKit Events – Modal Logic
 *
 * Expects window.CK_Events (Data Bridge from index.blade.php).
 * Rule: only classList operations — no el.style.*
 *
 * Exported globals:
 *   evtModalOpen()  — opens the quick-create modal (#evtModal)
 */
(function () {
    'use strict';

    const cfg    = window.CK_Events || {};
    const routes = cfg.routes || {};

    const form    = document.getElementById('evtForm');
    const titleEl = document.getElementById('evtTitle');

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Open the event quick-create modal and reset the form.
     */
    window.evtModalOpen = function () {
        if (form) form.reset();
        ckModalOpen('evtModal');
        if (titleEl) titleEl.focus();
    };

    // ── Form submit ───────────────────────────────────────────────────────────
    // Standard HTML form submit — no AJAX needed.
    // After store(), the controller redirects to the detail page.

}());
