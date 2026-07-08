/**
 * appearance-modal.js
 * Section-by-section AJAX save for the appearance settings page.
 *
 * Rules:
 *  - No el.style.* – CSS variables are updated inside <style id="ck-dynamic-css">
 *  - No inline style="" – only classList operations
 *  - Spinner via ck-btn--loading class
 *  - Feedback via ck-save-status class
 *  - IIFE: all functions private, no global scope leak
 */
(function () {
    'use strict';

    // ── Initialisation ────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        // Read the initial CSS variable state from the <style> block
        parseCssVarsFromStyleBlock();

        // Initialise all save buttons
        document.querySelectorAll('[data-appearance-save]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                saveSection(btn);
            });
        });
    });

    // ── CSS variable management ───────────────────────────────────────────────

    /**
     * Reads the currently active CSS variables from the <style id="ck-dynamic-css"> block.
     * Called once on page load.
     */
    function parseCssVarsFromStyleBlock() {
        const styleEl = document.getElementById('ck-dynamic-css');
        if (!styleEl) return;

        window.CK_Appearance = window.CK_Appearance || {};
        window.CK_Appearance.cssVars = window.CK_Appearance.cssVars || {};

        // Extract all "--ck-xxx: value;" entries
        const text    = styleEl.textContent;
        const pattern = /(--ck-[\w-]+)\s*:\s*([^;]+);/g;
        let match;
        while ((match = pattern.exec(text)) !== null) {
            window.CK_Appearance.cssVars[match[1].trim()] = match[2].trim();
        }
    }

    /**
     * Updates CSS variables inside the <style> block.
     * No el.style.* – the <style> block is rewritten entirely.
     *
     * @param {Object} newVars  { '--ck-brand-bar-bg': '#ff0000', ... }
     */
    function applyCssVars(newVars) {
        const styleEl = document.getElementById('ck-dynamic-css');
        if (!styleEl) return;

        // Merge new values into the stored state
        Object.keys(newVars).forEach(function (key) {
            window.CK_Appearance.cssVars[key] = newVars[key];
        });

        // Rewrite the <style> block
        const lines = Object.keys(window.CK_Appearance.cssVars).map(function (key) {
            return '        ' + key + ': ' + window.CK_Appearance.cssVars[key] + ';';
        });
        styleEl.textContent = ':root {\n' + lines.join('\n') + '\n    }';
    }

    // ── Save section ──────────────────────────────────────────────────────────

    /**
     * Collects all [data-setting] inputs within the nearest .ck-card,
     * sends them via AJAX and processes the response.
     *
     * @param {HTMLElement} btn  The clicked save button
     */
    function saveSection(btn) {
        const card     = btn.closest('.ck-card');
        const statusEl = card ? card.querySelector('[data-save-status]') : null;

        if (!card) return;

        // Collect form data
        const formData = new FormData();
        formData.append('_method', 'PATCH');
        formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

        card.querySelectorAll('[data-setting]').forEach(function (input) {
            if (input.type === 'checkbox') {
                // Checkboxes: '1' when checked, '0' when not
                formData.append(input.name, input.checked ? '1' : '0');
            } else if (input.type === 'file') {
                if (input.files && input.files.length > 0) {
                    formData.append(input.name, input.files[0]);
                }
            } else {
                formData.append(input.name, input.value);
            }
        });

        // Start spinner
        btn.classList.add('ck-btn--loading');
        btn.disabled = true;

        // AJAX request
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
                // Apply CSS variables immediately in the browser (no reload needed)
                if (data.cssVars && Object.keys(data.cssVars).length > 0) {
                    applyCssVars(data.cssVars);
                }

                if (data.needsReload) {
                    // Reload when emojis, logo or club name changed
                    showStatus(statusEl, ckUi('appearance_reloading', '↺ Wird neu geladen…'), 'reload');
                    setTimeout(function () { window.location.reload(); }, 1000);
                } else {
                    showStatus(statusEl, ckUi('appearance_saved', '✓ Gespeichert'), 'success');
                }
            } else {
                showStatus(statusEl, '✗ ' + (data.message || ckUi('appearance_error', 'Fehler')), 'error');
            }
        })
        .catch(function (err) {
            showStatus(statusEl, '✗ ' + (err.message || ckUi('appearance_network', 'Netzwerkfehler')), 'error');
        })
        .finally(function () {
            btn.classList.remove('ck-btn--loading');
            btn.disabled = false;
        });
    }

    // ── Status display ────────────────────────────────────────────────────────

    /**
     * Shows a status message and hides it after 3 seconds.
     *
     * @param {HTMLElement|null} el    The [data-save-status] element
     * @param {string}           text  Text to display
     * @param {string}           type  'success' | 'error' | 'reload'
     */
    function showStatus(el, text, type) {
        if (!el) return;

        // Reset classes
        el.classList.remove('ck-save-status--success', 'ck-save-status--error', 'ck-save-status--reload');
        el.classList.remove('ck-save-status--visible');

        el.textContent = text;
        el.classList.add('ck-save-status--' + type);

        // Short delay so the browser registers the remove before re-adding visible
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                el.classList.add('ck-save-status--visible');
            });
        });

        // Auto-hide after 3 s (not for reload state)
        if (type !== 'reload') {
            setTimeout(function () {
                el.classList.remove('ck-save-status--visible');
            }, 3000);
        }
    }

}());
