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

/* ESC schließt alle offenen Modals */
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('.ck-modal-overlay.ck-modal--open').forEach(function (m) {
        m.classList.remove('ck-modal--open');
    });
    document.body.classList.remove('ck-no-scroll');
});
