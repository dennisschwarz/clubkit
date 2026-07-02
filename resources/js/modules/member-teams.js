/**
 * member-teams.js
 * Handles the Teams tab inside the member modal.
 *
 * Listens for ck:member.modal.open (emitted by members-modal.js),
 * checks the correct team checkboxes for the opened member,
 * and saves via AJAX PUT to the syncMemberTeams endpoint.
 *
 * Save flow (matches the ClubKit UX spec for ALL modals):
 *   1. ckModalClose()   → modal disappears immediately, prevents double-submit.
 *   2. ckShowLoading()  → full-page loading overlay appears.
 *   3. fetch()          → AJAX PUT fires after modal is already gone.
 *   4. ckHideLoading()  → overlay dismissed.
 *   5. ckNotify()       → toast appears bottom-right with localised message.
 *
 * Notifications are localised via window.CK_Lang.notifications
 * (injected by layout.blade.php from lang/{locale}/notifications.php).
 *
 * Rules:
 *  - No el.style.*  → classList only.
 *  - No inline strings for user-facing messages → window.CK_Lang.
 */

(function () {
    'use strict';

    const bridge = window.CK_MemberTeamsBridge || {};

    // Tracks the currently opened member so save() knows the target.
    let _currentMemberId = null;

    // ── Listen for member modal open ──────────────────────────────────────

    document.addEventListener('ck:member.modal.open', function (e) {
        const detail = (e && e.detail) ? e.detail : {};
        _currentMemberId = detail.memberId || null;
        _syncCheckboxes(_currentMemberId);
    });

    // ── Sync checkboxes to current member's team assignments ──────────────

    /**
     * Check/uncheck team checkboxes to reflect the member's current assignments.
     *
     * @param {number|null} memberId
     */
    function _syncCheckboxes(memberId) {
        const checks  = document.querySelectorAll('.ck-member-team-check');
        const teamIds = memberId
            ? ((bridge.memberTeams || {})[memberId] || [])
            : [];

        for (let i = 0; i < checks.length; i++) {
            checks[i].checked = teamIds.indexOf(parseInt(checks[i].value, 10)) !== -1;
        }
    }

    // ── Save team assignments via AJAX ────────────────────────────────────

    /**
     * Collect checked team IDs and PUT them to the syncMemberTeams endpoint.
     *
     * Called by the "Speichern" button in member-modal-section.blade.php.
     *
     * Flow: close modal → show loading overlay → fetch → hide loading → notify.
     */
    window.memberTeamsSave = function () {
        if (!_currentMemberId) return;

        const checks  = document.querySelectorAll('.ck-member-team-check:checked');
        const teamIds = Array.from(checks).map(function (c) {
            return parseInt(c.value, 10);
        });

        const url  = (bridge.route || '') + '/' + _currentMemberId + '/sync';
        const csrf = document.querySelector('meta[name="csrf-token"]');
        const lang = ((window.CK_Lang || {}).notifications || {});

        // 1. Close the modal immediately (prevents double-submit).
        ckModalClose(null, 'memberModal');

        // 2. Show the full-page loading overlay.
        if (window.ckShowLoading) window.ckShowLoading();

        // 3. Fire the request.
        fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': csrf ? csrf.content : '',
            },
            body: JSON.stringify({ team_ids: teamIds }),
        })
        .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function () {
            // 4. Hide loading overlay.
            if (window.ckHideLoading) window.ckHideLoading();
            // 5. Show success toast.
            ckNotify('success', lang.teams_saved || 'Teams gespeichert.');
        })
        .catch(function () {
            // 4. Hide loading overlay on error too.
            if (window.ckHideLoading) window.ckHideLoading();
            // 5. Show error toast – modal stays closed, user can retry from the list.
            ckNotify('error', lang.teams_save_error || 'Fehler beim Speichern der Teams.');
        });
    };

}());
