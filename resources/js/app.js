/**
 * ClubKit – Global JS
 * Keine inline-Styles. Nur classList-Operationen.
 */

import 'flatpickr/dist/flatpickr.min.css'; // Vite löst aus node_modules auf
import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';

// Flatpickr: Globale Standardwerte für ClubKit
// - Deutsch, 24h-Format, 15-Minuten-Schritte, immer Custom-Picker (kein Mobile-Native)
flatpickr.setDefaults({
    locale: German,
    time_24hr: true,
    enableTime: true,
    dateFormat: 'Y-m-d H:i',
    altInput: true,
    altFormat: 'd.m.Y H:i',
    minuteIncrement: 15,
    disableMobile: true,
    monthSelectorType: 'static', // Kein Dropdown – verhindert den Split-Layout-Bug
    animate: false,              // Keine CSS-Transition (interferiert mit Modal-Animationen)
    closeOnSelect: false,        // Picker offen lassen bis "Übernehmen" geklickt
});

// Global verfügbar machen für standalone JS-Module (events-modal.js etc.)
window.flatpickr = flatpickr;

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Modal Teleport ──────────────────────────────────────────────────
    var modalRoot = document.getElementById('ck-modal-root');
    if (modalRoot) {
        document.querySelectorAll('.ck-modal-overlay').forEach(function (modal) {
            modalRoot.appendChild(modal);
        });
    }

    // ── 2. Flash-Messages auto-hide ────────────────────────────────────────
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () {
            el.classList.add('ck-flash--hiding');
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });

    // ── 3. Loading Overlay ─────────────────────────────────────────────────
    var loadingOverlay = document.getElementById('ck-loading-overlay');
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
        var link = e.target.closest('a[href]');
        if (!link) return;
        var href = link.getAttribute('href');
        if (!href || href === '' || href.startsWith('#') ||
            href.startsWith('javascript:') || link.target === '_blank') return;
        try {
            var url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
        } catch (_) { return; }
        if (link.getAttribute('onclick')) return;
        showLoading();
    });

    window.addEventListener('pageshow', function () {
        loadingOverlay.classList.remove('ck-loading--active');
    });

});

// ── Modal-Helpers ──────────────────────────────────────────────────────────

window.ckModalOpen = function (id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('ck-modal--open');
    document.body.classList.add('ck-no-scroll');
};

window.ckModalClose = function (e, id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    if (e && e.target !== modal) return;
    modal.classList.remove('ck-modal--open');
    document.body.classList.remove('ck-no-scroll');
};

window.ckModalTab = function (modalId, sectionId, btn) {
    var modal = document.getElementById(modalId);
    if (!modal) return;
    modal.querySelectorAll('.ck-modal__section').forEach(function (s) {
        s.classList.remove('ck-modal__section--active');
    });
    modal.querySelectorAll('.ck-modal-tab').forEach(function (b) {
        b.classList.remove('ck-modal-tab--active');
    });
    var target = document.getElementById(sectionId);
    if (target) target.classList.add('ck-modal__section--active');
    if (btn)    btn.classList.add('ck-modal-tab--active');
};

window.ckTabEnable = function (tabBtnId, hintId, enabled) {
    var tabBtn = document.getElementById(tabBtnId);
    var hint   = document.getElementById(hintId);
    if (!tabBtn) return;
    tabBtn.disabled = !enabled;
    tabBtn.classList.toggle('ck-modal-tab--disabled', !enabled);
    if (hint) hint.classList.toggle('is-hidden', enabled);
};

// ── Local Tab (Seiten-intern) ──────────────────────────────────────────────

window.ckLocalTab = function (sectionId, btn) {
    document.querySelectorAll('.ck-local-section').forEach(function (s) {
        s.classList.remove('ck-local-section--active');
    });
    document.querySelectorAll('.ck-local-tab').forEach(function (b) {
        b.classList.remove('ck-local-tab--active');
    });
    var target = document.getElementById(sectionId);
    if (target) target.classList.add('ck-local-section--active');
    if (btn)    btn.classList.add('ck-local-tab--active');
};

// ── Accordion: Tabellen-Zeilen / Sektionen aufklappen ─────────────────────
// Generischer Helfer: togglet is-hidden auf der Body-Zeile und
// rotiert das Chevron-Icon per ck-accordion-chevron--open.
//
// Verwendung (Blade):
//   onclick="ckAccordion('team-expand-5', 'chevron-5')"
//   – oder mit einem dedizierten Wrapper in Modulen (z.B. ckTeamAccordion)
//
// ckSectionToggle ist ein Alias für ckAccordion (identische Logik,
// verschiedene semantische Kontexte: Zeilen vs. Inhaltsblöcke).

window.ckAccordion = function (bodyId, chevronId) {
    var body    = document.getElementById(bodyId);
    var chevron = document.getElementById(chevronId);
    if (!body) return;
    body.classList.toggle('is-hidden');
    if (chevron) chevron.classList.toggle('ck-accordion-chevron--open');
};

// Alias: Management-Seite nutzt ckSectionToggle für Gruppen-Blöcke –
// Verhalten ist identisch zu ckAccordion.
window.ckSectionToggle = window.ckAccordion;

// ── ESC schließt alle offenen Modals ──────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.ck-modal-overlay.ck-modal--open').forEach(function (m) {
        m.classList.remove('ck-modal--open');
    });
    document.body.classList.remove('ck-no-scroll');
});

// ── Modul-Ereignis-System ─────────────────────────────────────────────────

window.ckEmit = function (event, data) {
    document.dispatchEvent(new CustomEvent('ck:' + event, { detail: data || {} }));
};

window.ckOn = function (event, handler) {
    document.addEventListener('ck:' + event, function (e) { handler(e.detail); });
};
