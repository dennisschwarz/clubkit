/**
 * ClubKit – Global JS
 * No inline styles. classList operations only.
 */

import 'flatpickr/dist/flatpickr.min.css'; // Vite resolves from node_modules
import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';

// Flatpickr: global defaults for ClubKit
// German locale, 24h format, 15-minute steps, always custom picker (no mobile native)
flatpickr.setDefaults({
    locale: German,
    time_24hr: true,
    enableTime: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'd.m.Y H:i',
    minuteIncrement: 15,
    disableMobile: true,
    monthSelectorType: 'static', // No dropdown – prevents split-layout bug
    animate: false,              // No CSS transition (interferes with modal animations)
    closeOnSelect: false,        // Keep picker open until confirmed
});

// Make globally available for standalone JS modules (events-modal.js etc.)
window.flatpickr = flatpickr;

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Modal Teleport ──────────────────────────────────────────────────
    const modalRoot = document.getElementById('ck-modal-root');
    if (modalRoot) {
        document.querySelectorAll('.ck-modal-overlay').forEach(function (modal) {
            modalRoot.appendChild(modal);
        });
    }

    // ── 2. Flash messages auto-hide ────────────────────────────────────────
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () {
            el.classList.add('ck-flash--hiding');
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // ── 3. Loading Overlay ─────────────────────────────────────────────────
    const loadingOverlay = document.getElementById('ck-loading-overlay');
    if (!loadingOverlay) return;

    function showLoading() {
        loadingOverlay.classList.add('ck-loading--active');
        setTimeout(function () {
            loadingOverlay.classList.remove('ck-loading--active');
        }, 20000);
    }

    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.tagName !== 'FORM') return;
        showLoading();
    });

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href]');
        if (!link) return;
        const href = link.getAttribute('href');
        if (!href || href === '' || href.startsWith('#') ||
            href.startsWith('javascript:') || link.target === '_blank') return;
        try {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
        } catch (_) { return; }
        if (link.getAttribute('onclick')) return;
        showLoading();
    });

    window.addEventListener('pageshow', function () {
        loadingOverlay.classList.remove('ck-loading--active');
    });

});

// ── Modal helpers ──────────────────────────────────────────────────────────

window.ckModalOpen = function (id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('ck-modal--open');
    document.body.classList.add('ck-no-scroll');
};

window.ckModalClose = function (e, id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    if (e && e.target !== modal) return;
    modal.classList.remove('ck-modal--open');
    document.body.classList.remove('ck-no-scroll');
};

window.ckModalTab = function (modalId, sectionId, btn) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    modal.querySelectorAll('.ck-modal__section').forEach(function (s) {
        s.classList.remove('ck-modal__section--active');
    });
    modal.querySelectorAll('.ck-modal-tab').forEach(function (b) {
        b.classList.remove('ck-modal-tab--active');
    });
    const target = document.getElementById(sectionId);
    if (target) target.classList.add('ck-modal__section--active');
    if (btn)    btn.classList.add('ck-modal-tab--active');
};

window.ckTabEnable = function (tabBtnId, hintId, enabled) {
    const tabBtn = document.getElementById(tabBtnId);
    const hint   = document.getElementById(hintId);
    if (!tabBtn) return;
    tabBtn.disabled = !enabled;
    tabBtn.classList.toggle('ck-modal-tab--disabled', !enabled);
    if (hint) hint.classList.toggle('is-hidden', enabled);
};

// ── Local tab (page-internal) ──────────────────────────────────────────────

window.ckLocalTab = function (sectionId, btn) {
    document.querySelectorAll('.ck-local-section').forEach(function (s) {
        s.classList.remove('ck-local-section--active');
    });
    document.querySelectorAll('.ck-local-tab').forEach(function (b) {
        b.classList.remove('ck-local-tab--active');
    });
    const target = document.getElementById(sectionId);
    if (target) target.classList.add('ck-local-section--active');
    if (btn)    btn.classList.add('ck-local-tab--active');
};

// ── Accordion: expand / collapse table rows or sections ───────────────────
// Generic helper: toggles is-hidden on body row and rotates chevron icon
// via ck-accordion-chevron--open.
//
// Usage (Blade):
//   onclick="ckAccordion('team-expand-5', 'chevron-5')"
//
// ckSectionToggle is an alias for ckAccordion (identical logic,
// different semantic contexts: rows vs. content blocks).

window.ckAccordion = function (bodyId, chevronId) {
    const body    = document.getElementById(bodyId);
    const chevron = document.getElementById(chevronId);
    if (!body) return;
    body.classList.toggle('is-hidden');
    if (chevron) chevron.classList.toggle('ck-accordion-chevron--open');
};

// Alias: Management page uses ckSectionToggle for group blocks –
// behaviour is identical to ckAccordion.
window.ckSectionToggle = window.ckAccordion;

// ── ESC closes all open modals ─────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.ck-modal-overlay.ck-modal--open').forEach(function (m) {
        m.classList.remove('ck-modal--open');
    });
    document.body.classList.remove('ck-no-scroll');
});

// ── Module event system ────────────────────────────────────────────────────

window.ckEmit = function (event, data) {
    document.dispatchEvent(new CustomEvent('ck:' + event, { detail: data || {} }));
};

window.ckOn = function (event, handler) {
    document.addEventListener('ck:' + event, function (e) { handler(e.detail); });
};
