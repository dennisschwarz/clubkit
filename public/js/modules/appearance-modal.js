/**
 * appearance-modal.js
 * Sektions-Speichern mit AJAX für die Erscheinungsbild-Seite.
 *
 * Regeln:
 *  - Kein el.style.* – CSS-Variablen werden im <style id="ck-dynamic-css"> aktualisiert
 *  - Kein inline style="" – nur classList-Operationen
 *  - Spinner via ck-btn--loading Klasse
 *  - Rückmeldung via ck-save-status Klasse
 */

document.addEventListener('DOMContentLoaded', function () {

    // Initialen CSS-Variablen-Stand aus dem <style>-Block einlesen
    parseCssVarsFromStyleBlock();

    // Alle Speichern-Buttons initialisieren
    document.querySelectorAll('[data-appearance-save]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            saveSection(btn);
        });
    });

});

// ── CSS-Variablen-Management ───────────────────────────────────────────────

/**
 * Aktuell aktive CSS-Variablen aus dem <style id="ck-dynamic-css">-Block lesen.
 * Wird einmalig beim Laden aufgerufen.
 */
function parseCssVarsFromStyleBlock() {
    var styleEl = document.getElementById('ck-dynamic-css');
    if (!styleEl) return;

    window.CK_Appearance = window.CK_Appearance || {};
    window.CK_Appearance.cssVars = window.CK_Appearance.cssVars || {};

    // Alle "--ck-xxx: wert;" Einträge extrahieren
    var text = styleEl.textContent;
    var pattern = /(--ck-[\w-]+)\s*:\s*([^;]+);/g;
    var match;
    while ((match = pattern.exec(text)) !== null) {
        window.CK_Appearance.cssVars[match[1].trim()] = match[2].trim();
    }
}

/**
 * CSS-Variablen im <style>-Block aktualisieren.
 * Keine el.style.* – der <style>-Block wird neu geschrieben.
 *
 * @param {Object} newVars  { '--ck-brand-bar-bg': '#ff0000', ... }
 */
function applyCssVars(newVars) {
    var styleEl = document.getElementById('ck-dynamic-css');
    if (!styleEl) return;

    // Neue Werte in den gespeicherten Stand einpflegen
    Object.keys(newVars).forEach(function (key) {
        window.CK_Appearance.cssVars[key] = newVars[key];
    });

    // <style>-Block neu schreiben
    var lines = Object.keys(window.CK_Appearance.cssVars).map(function (key) {
        return '        ' + key + ': ' + window.CK_Appearance.cssVars[key] + ';';
    });
    styleEl.textContent = ':root {\n' + lines.join('\n') + '\n    }';
}

// ── Sektion speichern ──────────────────────────────────────────────────────

/**
 * Sammelt alle [data-setting]-Inputs innerhalb der nächsten .ck-card,
 * schickt sie per AJAX und verarbeitet die Antwort.
 *
 * @param {HTMLElement} btn  Der geklickte Speichern-Button
 */
function saveSection(btn) {
    var card      = btn.closest('.ck-card');
    var statusEl  = card ? card.querySelector('[data-save-status]') : null;

    if (!card) return;

    // Daten sammeln
    var formData = new FormData();
    formData.append('_method', 'PATCH');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

    card.querySelectorAll('[data-setting]').forEach(function (input) {
        if (input.type === 'checkbox') {
            // Checkboxen: '1' wenn aktiviert, '0' wenn nicht
            formData.append(input.name, input.checked ? '1' : '0');
        } else if (input.type === 'file') {
            if (input.files && input.files.length > 0) {
                formData.append(input.name, input.files[0]);
            }
        } else {
            formData.append(input.name, input.value);
        }
    });

    // Spinner starten
    btn.classList.add('ck-btn--loading');
    btn.disabled = true;

    // AJAX-Request
    fetch(window.CK_Appearance.routes.update, {
        method:  'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body:    formData,
    })
    .then(function (response) {
        if (!response.ok) {
            return response.json().then(function (err) {
                throw new Error(err.message || 'HTTP ' + response.status);
            });
        }
        return response.json();
    })
    .then(function (data) {
        if (data.success) {

            // CSS-Variablen sofort im Browser anwenden (kein Reload nötig)
            if (data.cssVars && Object.keys(data.cssVars).length > 0) {
                applyCssVars(data.cssVars);
            }

            if (data.needsReload) {
                // Seite neu laden wenn Emojis, Logo oder Vereinsname geändert wurden
                showStatus(statusEl, '↺ Wird neu geladen…', 'reload');
                setTimeout(function () { window.location.reload(); }, 1000);
            } else {
                showStatus(statusEl, '✓ Gespeichert', 'success');
            }

        } else {
            showStatus(statusEl, '✗ ' + (data.message || 'Fehler'), 'error');
        }
    })
    .catch(function (err) {
        showStatus(statusEl, '✗ ' + (err.message || 'Netzwerkfehler'), 'error');
    })
    .finally(function () {
        btn.classList.remove('ck-btn--loading');
        btn.disabled = false;
    });
}

// ── Status-Anzeige ─────────────────────────────────────────────────────────

/**
 * Statusmeldung einblenden und nach 3 Sekunden ausblenden.
 *
 * @param {HTMLElement|null} el      Das [data-save-status]-Element
 * @param {string}           text    Anzuzeigender Text
 * @param {string}           type    'success' | 'error' | 'reload'
 */
function showStatus(el, text, type) {
    if (!el) return;

    // Klassen zurücksetzen
    el.classList.remove('ck-save-status--success', 'ck-save-status--error', 'ck-save-status--reload');
    el.classList.remove('ck-save-status--visible');

    el.textContent = text;
    el.classList.add('ck-save-status--' + type);

    // Kurze Verzögerung damit der Browser das Ausblenden registriert
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            el.classList.add('ck-save-status--visible');
        });
    });

    // Nur bei Erfolg und Fehler: nach 3 s ausblenden
    if (type !== 'reload') {
        setTimeout(function () {
            el.classList.remove('ck-save-status--visible');
        }, 3000);
    }
}
