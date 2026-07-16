/**
 * ClubKit – Global JS
 * No inline styles. classList operations only.
 */

import 'flatpickr/dist/flatpickr.min.css'; // Vite resolves from node_modules
import flatpickr from 'flatpickr';
import { German } from 'flatpickr/dist/l10n/de.js';
import Sortable from 'sortablejs';

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
    allowInput: true,            // Bug-Fix: User can type directly into the altInput field.
                                 // Without this, the altInput is read-only and the user
                                 // must always open the picker via the calendar icon.
});

// Make globally available for standalone JS modules
window.flatpickr = flatpickr;
window.Sortable  = Sortable;

/**
 * Liest einen UI-String aus window.CK_Lang.ui.
 * Gibt den deutschen Fallback zurück, falls der Bridge-Key fehlt.
 *
 * @param  {string} key      - Schlüssel aus window.CK_Lang.ui
 * @param  {string} fallback - Deutscher Fallback-Text
 * @return {string}
 */
window.ckUi = function (key, fallback) {
    return (((window.CK_Lang || {}).ui || {})[key]) || fallback;
};

// ── Global confirm modal state ─────────────────────────────────────────────
// Shared callback reference: set before opening the confirm modal,
// cleared after the user confirms or cancels.
window._ckConfirmCallback = null;  // window-scoped so ckConfirmCancel() can clear it

/**
 * Open the global confirm modal with a message and execute a callback on confirm.
 *
 * Used by JS modules that need a blocking confirmation before an async operation
 * (e.g. youth-club-mode.js fetch DELETE). HTML form deletes are handled automatically
 * via the [data-ck-confirm] attribute on <x-ck-button :confirm="..."> elements.
 *
 * @param {string}   message  - Text shown in the modal body.
 * @param {Function} callback - Called when the user clicks "Ja, löschen".
 */
window.ckConfirm = function (message, callback) {
    const textEl = document.getElementById('ck-confirm-text');
    if (textEl) textEl.textContent = message;
    window._ckConfirmCallback = callback;
    ckModalOpen('ck-confirm-overlay');
};

// ── HTML escape helper ─────────────────────────────────────────────────────

/**
 * Escapes HTML special characters to prevent XSS in dynamically built HTML.
 * Used internally by ckNotify().
 *
 * @param  {string} str
 * @return {string}
 */
function _ckEscHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Toast notification ─────────────────────────────────────────────────────

/**
 * Display a fixed-position toast notification in the bottom-right corner.
 *
 * Creates #ck-toast-container if it does not yet exist.
 * CSS: resources/css/components/misc.css → .ck-toast / #ck-toast-container.
 *
 * Called by:
 *   - DOMContentLoaded: reads window.CK_Notifications (server flash bridge).
 *   - AJAX modules (member-teams.js, youth-club-mode.js etc.) after fetch().
 *
 * @param {'success'|'error'|'warning'|'info'} type
 * @param {string} message
 * @param {number} [duration=7000]  Auto-dismiss delay in milliseconds.
 */
window.ckNotify = function (type, message, duration) {
    if (!message) return;
    duration = duration || 7000;

    // Ensure the container exists (created once, persists for the page lifetime).
    let container = document.getElementById('ck-toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'ck-toast-container';
        document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '⚠️', warning: '🔔', info: 'ℹ️' };

    const toast = document.createElement('div');
    toast.className  = 'ck-toast ck-toast--' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML  =
        '<span class="ck-toast__icon">' + (icons[type] || '') + '</span>'
        + '<span class="ck-toast__msg">' + _ckEscHtml(message) + '</span>'
        + '<button type="button" class="ck-toast__close" '
        + 'onclick="this.closest(\'.ck-toast\').classList.add(\'ck-toast--hiding\')"'
        + '>&times;</button>';

    container.appendChild(toast);

    // Double rAF ensures the enter transition fires after the element is painted.
    requestAnimationFrame(function () {
        requestAnimationFrame(function () {
            toast.classList.add('ck-toast--visible');
        });
    });

    // Auto-dismiss after [duration] ms.
    const timer = setTimeout(function () { _ckDismissToast(toast); }, duration);

    // Click anywhere on the toast to dismiss early.
    toast.addEventListener('click', function () {
        clearTimeout(timer);
        _ckDismissToast(toast);
    });
};

/**
 * Animates a toast out and removes it from the DOM.
 *
 * @param {HTMLElement} toast
 */
function _ckDismissToast(toast) {
    toast.classList.remove('ck-toast--visible');
    toast.classList.add('ck-toast--hiding');
    setTimeout(function () {
        if (toast.parentNode) toast.remove();
    }, 400);
}

// ── Local tab switcher (Management, Treasury) ──────────────────────────────

/**
 * Switch between local page-level sub-tabs.
 *
 * Different from ckModalTab(): local tabs are NOT inside a modal overlay.
 * Used on pages that have a .ck-local-tabs bar and .ck-local-section panels
 * as siblings within the same parent element (e.g. Management, Treasury).
 *
 * Behaviour:
 *   1. Deactivate all .ck-local-tab buttons within the same tab bar.
 *   2. Activate the clicked button.
 *   3. Hide all .ck-local-section elements that are direct children of the
 *      tab bar's parent element (i.e. siblings of the tab bar).
 *   4. Show the target section.
 *
 * @param {string}      sectionId  - ID of the .ck-local-section to reveal.
 * @param {HTMLElement} btn        - The clicked tab button.
 */
window.ckLocalTab = function (sectionId, btn) {
    // 1+2. Deactivate all tabs in the same bar, activate the clicked one.
    const tabBar = btn ? btn.closest('.ck-local-tabs') : null;
    if (tabBar) {
        tabBar.querySelectorAll('.ck-local-tab').forEach(function (b) {
            b.classList.remove('ck-local-tab--active');
        });
        if (btn) btn.classList.add('ck-local-tab--active');

        // 3. Hide all .ck-local-section siblings of the tab bar's parent container.
        const parent = tabBar.closest('.ck-local-tabs-bar') || tabBar.parentElement;
        if (parent) {
            parent.querySelectorAll(':scope > .ck-local-section').forEach(function (s) {
                s.classList.remove('ck-local-section--active');
            });
        }
    }

    // 4. Toggle .ck-event-tab-action header groups — same pattern as ckEvtTab()
    //    in events/globals.js.  The tab ID is the part of the sectionId after the
    //    first dash (e.g. "mgmtTab-funktionen" → "funktionen").
    var tabId = sectionId.replace(/^[^-]+-/, '');
    document.querySelectorAll('.ck-event-tab-action').forEach(function (a) {
        var multi = (a.dataset.ckTabActions || '').split(' ').filter(Boolean);
        var match = multi.length > 0
            ? multi.indexOf(tabId) !== -1
            : a.id === 'ckEvtAction-' + tabId;
        a.classList.toggle('ck-event-tab-action--active', match);
    });

    // 5. Show the target section.
    const section = document.getElementById(sectionId);
    if (section) section.classList.add('ck-local-section--active');
};

// ── Flatpickr calendar icon trigger helper ────────────────────────────────

/**
 * Wraps the flatpickr altInput in a flex container and appends a calendar icon
 * button that opens/closes the picker.
 *
 * This restores the "icon next to the field" UX that was present in earlier
 * versions. The icon is the only way to open the picker when allowInput=true
 * lets the user type directly into the text field.
 *
 * DOM result:
 *   <div class="ck-fp-wrap">
 *     <input class="flatpickr-input ck-field__input" ...>   ← altInput
 *     <button class="ck-fp-trigger" aria-label="Kalender öffnen">📅</button>
 *   </div>
 *
 * @param {Object} instance - Flatpickr instance (from onReady callback).
 */
function _ckFpAddTrigger(instance) {
    var altInput = instance.altInput;
    if (!altInput) { return; }

    // Create the wrapper and insert it where the altInput currently is.
    var wrap = document.createElement('div');
    wrap.className = 'ck-fp-wrap';
    altInput.parentNode.insertBefore(wrap, altInput);
    wrap.appendChild(altInput);

    // Calendar icon trigger button.
    var trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ck-fp-trigger';
    trigger.setAttribute('aria-label', ckUi('fp_calendar_aria', 'Kalender öffnen'));
    trigger.setAttribute('tabindex', '-1'); // Not in tab order – field itself is focusable.
    trigger.textContent = '📅';
    trigger.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        instance.toggle();
    });
    wrap.appendChild(trigger);
}

document.addEventListener('DOMContentLoaded', function () {

    // ── 1. Modal Teleport ──────────────────────────────────────────────────
    const modalRoot = document.getElementById('ck-modal-root');
    if (modalRoot) {
        document.querySelectorAll('.ck-modal-overlay').forEach(function (modal) {
            modalRoot.appendChild(modal);
        });
    }

    // ── 2. Flash messages: toast or auto-hide HTML fallback ───────────────
    //
    // If window.CK_Notifications is set (output by layout.blade.php), show each
    // entry as a ckNotify() toast and remove the HTML .ck-flash fallback elements.
    //
    // If the bridge is absent (no flash session data), fall through to the
    // existing auto-hide behavior for any .ck-flash elements that are present.
    if (window.CK_Notifications && window.CK_Notifications.length) {
        // JS users: remove HTML fallback elements immediately (they are inside
        // a <noscript> block in the layout so non-JS users still see them).
        document.querySelectorAll('[data-flash]').forEach(function (el) { el.remove(); });

        // Stagger toasts slightly so they don't all appear at the same instant.
        window.CK_Notifications.forEach(function (n, i) {
            if (n && n.type && n.message) {
                setTimeout(function () { ckNotify(n.type, n.message); }, 150 + i * 200);
            }
        });
    } else {
        // No bridge: auto-hide any HTML flash elements after 4 seconds.
        document.querySelectorAll('[data-flash]').forEach(function (el) {
            setTimeout(function () {
                el.classList.add('ck-flash--hiding');
                setTimeout(function () { el.remove(); }, 400);
            }, 4000);
        });
    }

    // ── 3. Flatpickr pickers ────────────────────────────────────────────────
    //
    // Date-only  → [type="date"]      → enableTime: false, format: d.m.Y
    // Date+time  → [data-ck-datetime] → enableTime: true  (global default), format: d.m.Y H:i
    //
    // Global flatpickr.setDefaults() (top of file) supplies locale, altInput and
    // disableMobile for both. data-ck-datetime is forwarded via {{ $attributes }} in
    // x-ck-field whenever the caller adds data-ck-datetime="1" to the component.

    document.querySelectorAll('input[type="date"]').forEach(function (el) {
        flatpickr(el, {
            enableTime: false,
            dateFormat: 'Y-m-d',
            altFormat:  'd.m.Y',
            closeOnSelect: true, // Date-only: close immediately after selection (no time to confirm).
            onReady: function (selectedDates, dateStr, instance) {
                // Wrap the altInput with a calendar icon trigger button.
                // This restores the icon the user previously had and allows
                // opening the picker without clicking directly on the text field.
                _ckFpAddTrigger(instance);
            },
        });
    });

    document.querySelectorAll('[data-ck-datetime]').forEach(function (el) {
        flatpickr(el, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            altFormat:  'd.m.Y H:i',
            onReady: function (selectedDates, dateStr, instance) {
                // Wrap the altInput with a calendar icon trigger button.
                _ckFpAddTrigger(instance);

                // "Apply" confirm button appended below the time picker.
                // Bug-Fix: Button is DISABLED initially — it only enables once the user
                // has actually selected a date. Previously, the button was always active
                // which allowed closing the picker without having selected anything.
                var confirmBtn          = document.createElement('button');
                confirmBtn.type         = 'button';
                confirmBtn.className    = 'ck-fp-confirm';
                confirmBtn.textContent  = '✓ ' + ckUi('fp_confirm_btn', 'Datum und Uhrzeit übernehmen');
                confirmBtn.disabled     = true; // Disabled until a date is chosen.
                confirmBtn.classList.add('ck-fp-confirm--disabled');
                confirmBtn.addEventListener('click', function () { instance.close(); });
                instance.calendarContainer.appendChild(confirmBtn);
                // Marker class so CSS can remove the bottom radius from .flatpickr-time
                instance.calendarContainer.classList.add('has-confirm-btn');

                // Store reference so onChange / onClear can toggle it.
                instance._ckConfirmBtn = confirmBtn;
            },
            onChange: function (selectedDates, dateStr, instance) {
                // Enable the confirm button as soon as a date is selected.
                if (instance._ckConfirmBtn) {
                    var hasDate = selectedDates.length > 0;
                    instance._ckConfirmBtn.disabled = !hasDate;
                    instance._ckConfirmBtn.classList.toggle('ck-fp-confirm--disabled', !hasDate);
                }
            },
            onClear: function (selectedDates, dateStr, instance) {
                // Re-disable the confirm button when the picker is cleared.
                if (instance._ckConfirmBtn) {
                    instance._ckConfirmBtn.disabled = true;
                    instance._ckConfirmBtn.classList.add('ck-fp-confirm--disabled');
                }
            },
        });
    });

    // Clear flatpickr altInputs when a parent form is reset (modal open → form.reset()).
    // Without this, the visible altInput retains the old value even after reset.
    document.addEventListener('reset', function (e) {
        if (!e.target || e.target.tagName !== 'FORM') { return; }
        e.target.querySelectorAll('input[type="date"], [data-ck-datetime]').forEach(function (el) {
            if (el._flatpickr) { el._flatpickr.clear(); }
        });
    });

    // ── 4. Loading Overlay ─────────────────────────────────────────────────
    const loadingOverlay = document.getElementById('ck-loading-overlay');
    if (!loadingOverlay) return;

    function showLoading() {
        loadingOverlay.classList.add('ck-loading--active');
        // Safety timeout: remove overlay after 20 s in case the request stalls.
        setTimeout(function () {
            loadingOverlay.classList.remove('ck-loading--active');
        }, 20000);
    }

    // Expose loading controls globally so AJAX modules can use them.
    // Pattern: ckModalClose() → ckShowLoading() → fetch() → ckHideLoading() → ckNotify()
    window.ckShowLoading = showLoading;
    window.ckHideLoading = function () {
        loadingOverlay.classList.remove('ck-loading--active');
    };

    /**
     * Global form-submit guard.
     *
     * Fired for every <form> submission in the document.
     * Order of operations (per UX spec):
     *   1. Disable all submit buttons immediately – prevents double-submit.
     *   2. Close the parent modal overlay instantly – modal disappears before
     *      the network request leaves the browser.
     *   3. Show the full-page loading overlay.
     *
     * This single handler covers every modal uniformly.
     * It does NOT call e.preventDefault() – the form still submits normally.
     */
    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.tagName !== 'FORM') return;

        // 1. Disable every submit button inside this form right away.
        e.target.querySelectorAll('[type="submit"]').forEach(function (btn) {
            btn.disabled = true;
        });

        // 2. If the form lives inside a modal overlay, close that overlay immediately.
        const overlay = e.target.closest('.ck-modal-overlay');
        if (overlay) {
            overlay.classList.remove('ck-modal--open');
            document.body.classList.remove('ck-no-scroll');
        }

        // 3. Show the full-page loading overlay.
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

    // ── 5. Global confirm modal ────────────────────────────────────────────

    /**
     * "Ja, löschen" button in the global confirm modal (#ck-confirm-overlay).
     *
     * Order of operations:
     *   1. Close the confirm modal immediately.
     *   2. Execute the stored callback (form.requestSubmit() or async fetch).
     *      requestSubmit() triggers the submit event → form-submit guard above
     *      disables buttons and shows the loading overlay automatically.
     */
    const confirmOkBtn = document.getElementById('ck-confirm-ok');
    if (confirmOkBtn) {
        confirmOkBtn.addEventListener('click', function () {
            ckModalClose(null, 'ck-confirm-overlay');
            if (typeof window._ckConfirmCallback === 'function') {
                const fn = window._ckConfirmCallback;
                window._ckConfirmCallback = null;
                fn();
            }
        });
    }

    /**
     * Global [data-ck-confirm] click handler.
     *
     * Intercepts clicks on any button rendered by <x-ck-button :confirm="...">
     * (which have type="button" and data-ck-confirm="message").
     * Opens the global confirm modal; on confirmation submits the correct form.
     *
     * Two submission strategies:
     *
     * A) Shared-form pattern (preferred for table list views):
     *    Button carries data-delete-url="..." in addition to data-ck-confirm.
     *    No inline <form> wrapper is needed around the button. On confirmation,
     *    the global #ck-delete-form (in layout.blade.php) has its action set
     *    dynamically and is submitted via requestSubmit().
     *    → Avoids the block-element layout break caused by per-row <form> tags
     *      inside <td class="ck-table__action-cell">.
     *
     * B) Inline-form fallback (legacy / non-table contexts):
     *    Button is wrapped in its own <form>. On confirmation, btn.closest('form')
     *    is used and requestSubmit() is called on it.
     *
     * requestSubmit() (both paths) – unlike form.submit() – fires the 'submit'
     * event, so the form-submit guard (disable buttons, loading overlay) triggers.
     */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-ck-confirm]');
        if (!btn) return;

        e.preventDefault();

        // Stop all other document-level click handlers from firing for this click.
        // Without this, AJAX delete handlers (e.g. events/task-modal.js) would fire
        // immediately alongside the confirm dialog.
        e.stopImmediatePropagation();

        const message   = btn.getAttribute('data-ck-confirm');
        const deleteUrl = btn.getAttribute('data-delete-url');
        const form      = btn.closest('form');

        window.ckConfirm(message, function () {
            if (deleteUrl) {
                // Strategy A: shared-form pattern.
                const sharedForm = document.getElementById('ck-delete-form');
                if (sharedForm) {
                    sharedForm.action = deleteUrl;
                    sharedForm.requestSubmit();
                }
                return;
            }

            if (form) {
                // Strategy B: inline-form fallback.
                form.requestSubmit();
                return;
            }

            // Strategy C: pure AJAX button (no form, no data-delete-url).
            // Remove the attribute so the re-click isn't intercepted again,
            // then re-trigger the click so the module-level handler fires.
            btn.removeAttribute('data-ck-confirm');
            btn.click();
            // The page will reload after the AJAX call; no need to restore the attribute.
        });
    });

});

// ── Color picker: sync ck-color-swatch--selected when radio changes ─────────
// Handles all .ck-color-picker instances globally (newCatModal, renameCatModal, etc.)
document.addEventListener('change', function (e) {
    if (! e.target.matches('.ck-color-picker input[type="radio"]')) { return; }
    var picker = e.target.closest('.ck-color-picker');
    if (! picker) { return; }
    picker.querySelectorAll('.ck-color-swatch').forEach(function (swatch) {
        swatch.classList.remove('ck-color-swatch--selected');
    });
    var activeSwatch = e.target.closest('.ck-color-swatch');
    if (activeSwatch) { activeSwatch.classList.add('ck-color-swatch--selected'); }
});

// ── Modal helpers ──────────────────────────────────────────────────────────
/**
 * Cancel the global confirm modal without executing the callback.
 * Call this from cancel buttons and backdrop clicks on the confirm modal.
 * Clearing _ckConfirmCallback prevents the callback from firing later.
 */
window.ckConfirmCancel = function () {
    window._ckConfirmCallback = null;
    ckModalClose(null, 'ck-confirm-overlay');
};


window.ckModalOpen = function (id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('ck-modal--open');
    document.body.classList.add('ck-no-scroll');
};

window.ckModalClose = function (e, id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    // Locked while an async request is in progress — ignore all close attempts.
    if (modal.classList.contains('ck-modal--locked')) return;
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
    if (btn) btn.classList.add('ck-modal-tab--active');
};

/**
 * Enable or disable a modal tab button, toggling the associated hint element.
 *
 * When disabled: button receives 'is-disabled', hint loses 'is-hidden'.
 * When enabled:  button loses 'is-disabled',   hint receives 'is-hidden'.
 *
 * @param {string}  tabBtnId - ID of the tab button element.
 * @param {string}  hintId   - ID of the hint element shown when the tab is disabled.
 * @param {boolean} enabled  - Whether the tab should be interactive.
 */
window.ckTabEnable = function (tabBtnId, hintId, enabled) {
    const btn  = document.getElementById(tabBtnId);
    const hint = document.getElementById(hintId);
    if (btn)  btn.classList.toggle('is-disabled', !enabled);
    if (hint) hint.classList.toggle('is-hidden',   enabled);
};

/**
 * Dispatch a namespaced CustomEvent on the document.
 *
 * Consumed by extension modules listening via document.addEventListener('ck:event.name').
 * The 'ck:' prefix is added automatically so callers use short event names.
 *
 * @param {string} event  - Short event name, e.g. 'member.modal.open'.
 * @param {object} detail - Arbitrary payload passed to listeners.
 */
window.ckEmit = function (event, detail) {
    document.dispatchEvent(new CustomEvent('ck:' + event, { detail: detail || {} }));
};

// ── Section / card accordion ─────────────────────────────────────────────────
// Shared toggle used by Teams accordion (ckSectionToggle) and collapsible
// x-ck-card components (delegated click on .ck-card__header--collapsible).

window.ckSectionToggle = function (bodyId, chevronId) {
    var body    = document.getElementById(bodyId);
    var chevron = document.getElementById(chevronId);
    if (body)    body.classList.toggle('is-hidden');
    if (chevron) chevron.classList.toggle('ck-accordion-chevron--open');
};

// Delegated handler for collapsible x-ck-card headers.
// The header carries data-body and data-chevron set by the Blade component.
document.addEventListener('click', function (e) {
    var header = e.target.closest('.ck-card__header--collapsible');
    if (! header) return;
    var bodyId    = header.dataset.body;
    var chevronId = header.dataset.chevron;
    var body      = bodyId    ? document.getElementById(bodyId)    : null;
    var chevron   = chevronId ? document.getElementById(chevronId) : null;
    if (body)    body.classList.toggle('is-hidden');
    if (chevron) chevron.classList.toggle('ck-accordion-chevron--open');
});