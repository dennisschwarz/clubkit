/**
 * ClubKit – Global JS
 * Keine inline-Styles. Nur classList-Operationen.
 */

/* ── Flash-Messages auto-hide ─────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-flash]').forEach(function (el) {
        setTimeout(function () {
            el.classList.add('ck-flash--hiding');
            setTimeout(function () { el.remove(); }, 400);
        }, 4000);
    });
});

/* ── Modal-Helpers (global) ───────────────────── */

/**
 * Modal öffnen
 * @param {string} id  – ID des .ck-modal-overlay Elements
 */
window.ckModalOpen = function (id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('ck-modal--open');
    document.body.classList.add('ck-no-scroll');
};

/**
 * Modal schließen
 * @param {Event|null} e  – Click-Event (prüft ob Overlay-Klick)
 * @param {string}     id – ID des .ck-modal-overlay Elements
 */
window.ckModalClose = function (e, id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    if (e && e.target !== modal) return;
    modal.classList.remove('ck-modal--open');
    document.body.classList.remove('ck-no-scroll');
};

/**
 * Modal-Tab wechseln
 * @param {string} modalId    – ID des Modals
 * @param {string} sectionId  – ID der anzuzeigenden .ck-modal__section
 * @param {HTMLElement} btn   – geklickter Tab-Button
 */
window.ckModalTab = function (modalId, sectionId, btn) {
    var modal = document.getElementById(modalId);
    if (!modal) return;

    // Alle Sections verstecken
    modal.querySelectorAll('.ck-modal__section').forEach(function (s) {
        s.classList.remove('ck-modal__section--active');
    });
    // Alle Tab-Buttons deaktivieren
    modal.querySelectorAll('.ck-modal-tab').forEach(function (b) {
        b.classList.remove('ck-modal-tab--active');
    });

    // Gewünschte Section zeigen + Button aktivieren
    var target = document.getElementById(sectionId);
    if (target) target.classList.add('ck-modal__section--active');
    if (btn)    btn.classList.add('ck-modal-tab--active');
};

/**
 * Modal-Tab aktivieren oder deaktivieren.
 * Wird von members-modal.js und anderen Modul-JS-Dateien genutzt.
 *
 * @param {string}  tabBtnId  – ID des Tab-Buttons
 * @param {string}  hintId    – ID des Hinweis-Elements (z. B. "erst speichern")
 * @param {boolean} enabled   – true = aktiv, false = deaktiviert
 */
window.ckTabEnable = function (tabBtnId, hintId, enabled) {
    var tabBtn = document.getElementById(tabBtnId);
    var hint   = document.getElementById(hintId);
    if (!tabBtn) return;
    tabBtn.disabled = !enabled;
    tabBtn.classList.toggle('ck-modal-tab--disabled', !enabled);
    if (hint) hint.classList.toggle('is-hidden', enabled);
};

/* ── ESC schließt alle offenen Modals ──────────── */
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.ck-modal-overlay.ck-modal--open').forEach(function (m) {
        m.classList.remove('ck-modal--open');
    });
    document.body.classList.remove('ck-no-scroll');
});

/* ── Modul-Ereignis-System ────────────────────── */

/**
 * Ereignis auslösen – für die Kommunikation zwischen Modulen.
 * Ein Modul teilt einem anderen mit, was gerade passiert,
 * ohne es direkt zu kennen.
 *
 * Beispiel in members-modal.js:
 *   ckEmit('member.modal.open', { mode: 'edit', memberId: 5, member: {...} });
 *
 * @param {string} event  Ereignis-Name (z. B. 'member.modal.open')
 * @param {object} data   Beliebige Daten
 */
window.ckEmit = function (event, data) {
    document.dispatchEvent(new CustomEvent('ck:' + event, { detail: data || {} }));
};

/**
 * Auf ein Modul-Ereignis lauschen.
 *
 * Beispiel in youth-club-mode.js:
 *   ckOn('member.modal.open', function(detail) { ... });
 *
 * @param {string}   event    Ereignis-Name
 * @param {function} handler  Erhält das detail-Objekt
 */
window.ckOn = function (event, handler) {
    document.addEventListener('ck:' + event, function (e) { handler(e.detail); });
};
