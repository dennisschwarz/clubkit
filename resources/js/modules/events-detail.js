/**
 * ClubKit – Event Detail Page
 *
 * Entry point. Imports sub-modules from events/ and initialises them
 * with a shared context object { cfg, csrf, Sortable, closest, reloadKeepingTab }.
 *
 * Sub-modules:
 *   events/globals.js       — window.ck* functions (onclick="..." targets)
 *   events/task-list.js     — Checkbox, SortableJS row D&D, ETM remove, progress
 *   events/task-modal.js    — New/edit/import task modal, select population
 *   events/categories.js    — Category create / rename / delete
 *   events/assign-modal.js  — SortableJS member assignment modal (task-tab)
 *   events/slot-modal.js    — Einsatzplan slot add / remove
 *   events/functions-tab.js — Funktionen tab AJAX
 *   events/teams-tab.js     — Teams tab AJAX
 *
 * Rules:
 *   - No el.style.property assignments → use classList only
 *   - CSS custom properties set via setProperty() (only permitted el.style.* usage)
 *   - All fetch() calls use window.CK_EventDetail for config (data bridge in show.blade.php)
 */

import Sortable from 'sortablejs';

import { initGlobals }      from './events/globals.js';
import { initTaskList }     from './events/task-list.js';
import { initTaskModal }    from './events/task-modal.js';
import { initCategories }   from './events/categories.js';
import { initAssignModal }  from './events/assign-modal.js';
import { initSlotModal }    from './events/slot-modal.js';
import { initFunctionsTab } from './events/functions-tab.js';
import { initTeamsTab }     from './events/teams-tab.js';

// Register global onclick="..." targets on window.
initGlobals(Sortable);

(function () {
    'use strict';

    var cfg = window.CK_EventDetail;
    if (! cfg) { return; }

    var csrf = cfg.csrf;

    // ── Restore active tab after AJAX-triggered page reload ───────────────────

    var _restoredTab = sessionStorage.getItem('ck_evt_active_tab');
    if (_restoredTab) {
        sessionStorage.removeItem('ck_evt_active_tab');
        var _restoredBtn = document.querySelector(
            '.ck-local-tab[onclick*="\'' + _restoredTab + '\'"]'
        );
        if (_restoredBtn) { ckEvtTab(_restoredTab, _restoredBtn); }
    }

    // ── KW navigation: initialise state from DOM ──────────────────────────────

    window.ckInitKwState();

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Walks up the DOM and returns the first ancestor (or self) matching selector.
     *
     * @param  {Element|null} el
     * @param  {string}       selector
     * @return {Element|null}
     */
    function closest(el, selector) {
        while (el && ! el.matches(selector)) {
            el = el.parentElement;
        }
        return el || null;
    }

    /**
     * Persists the active tab ID to sessionStorage and reloads the page.
     */
    function reloadKeepingTab() {
        var activePane = document.querySelector('.ck-local-section.ck-local-section--active');
        if (activePane) {
            sessionStorage.setItem('ck_evt_active_tab', activePane.id.replace('ckEvtPane-', ''));
        }
        window.location.reload();
    }

    // ── Shared context passed to all sub-modules ──────────────────────────────

    var ctx = { cfg: cfg, csrf: csrf, Sortable: Sortable, closest: closest, reloadKeepingTab: reloadKeepingTab };

    // ── Initialise sub-modules ────────────────────────────────────────────────

    initTaskList(ctx);
    initTaskModal(ctx);
    initCategories(ctx);
    initAssignModal(ctx);
    initSlotModal(ctx);
    initFunctionsTab(ctx);
    initTeamsTab(ctx);

}());