/**
 * Treasury Modal Module
 *
 * Handles all client-side interactions for the Treasury module:
 *   - Zusammenfassung tab: account dropdown filtering (stats + recent transactions)
 *   - Transaction create/edit modal
 *   - Account create/edit modal (with visibility-based team section toggle)
 *   - Contribution task assignment modal
 *   - Payment member assignment modal
 *
 * Relies on window.CK_Treasury (data bridge from treasury::index view).
 * Uses ckModalOpen() / ckModalClose() from resources/js/app.js.
 *
 * RULES:
 *   - No el.style.* — visibility/state managed via classList only.
 *   - No inline style attributes.
 *   - const/let only — no var.
 */

/* global CK_Treasury, ckModalOpen, ckModalClose */

(function () {
    'use strict';

    // ── Number formatting ─────────────────────────────────────────────────────

    /**
     * Formats a number in German locale (1.234,56 €).
     *
     * @param   {number} value
     * @returns {string}
     */
    function formatEuro(value) {
        return new Intl.NumberFormat('de-DE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value) + ' €';
    }

    // ── Zusammenfassung: account dropdown ─────────────────────────────────────

    /**
     * Updates the Zusammenfassung stats and recent transaction table
     * based on the selected account ID (empty string = all accounts).
     *
     * Called by the onchange handler of #summaryAccountDropdown.
     *
     * @param {string} accountId - selected option value, '' for all
     */
    window.treasurySummaryFilter = function (accountId) {
        const data = window.CK_Treasury || {};
        let stats;

        if (accountId && data.accountStats && data.accountStats[accountId]) {
            stats = data.accountStats[accountId];
        } else {
            stats = data.globalStats || { income: 0, expense: 0, balance: 0 };
        }

        // Update stat card values via textContent (not style)
        const elIncome  = document.getElementById('summaryIncome');
        const elExpense = document.getElementById('summaryExpense');
        const elBalance = document.getElementById('summaryBalance');

        if (elIncome)  { elIncome.textContent  = formatEuro(stats.income);  }
        if (elExpense) { elExpense.textContent  = formatEuro(stats.expense); }
        if (elBalance) { elBalance.textContent  = formatEuro(stats.balance); }

        // Toggle balance card colour class (no el.style.*)
        const balCard = document.getElementById('summaryBalanceCard');
        if (balCard) {
            balCard.classList.remove('ck-stat-card--blue', 'ck-stat-card--orange');
            balCard.classList.add(stats.balance >= 0 ? 'ck-stat-card--blue' : 'ck-stat-card--orange');
        }

        // Update recent transactions table
        updateRecentTable(accountId ? parseInt(accountId, 10) : null);
    };

    /**
     * Filters and re-renders the recent transactions tbody.
     * Shows all when accountId is null; filters by account_id otherwise.
     *
     * @param {number|null} accountId
     */
    function updateRecentTable(accountId) {
        const tbody = document.getElementById('summaryRecentTbody');
        if (! tbody) { return; }

        const all  = (window.CK_Treasury || {}).recentTransactions || [];
        const list = accountId
            ? all.filter(function (tx) { return tx.account_id === accountId; })
            : all;

        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="ck-empty-state">Keine Buchungen für diese Kasse.</td></tr>';
            return;
        }

        let rows = '';
        list.forEach(function (tx) {
            const amtClass = tx.type === 'income' ? 'ck-amount--income' : 'ck-amount--expense';
            const sign     = tx.type === 'income' ? '+' : '−';
            const catCell  = tx.category_name
                ? '<span class="ck-badge ck-badge--' + (tx.category_color || 'gray') + '">' + escapeHtml(tx.category_name) + '</span>'
                : '<span class="ck-muted">–</span>';

            rows += '<tr>'
                + '<td>' + escapeHtml(tx.transaction_date || '') + '</td>'
                + '<td>' + escapeHtml(tx.account_name || '–') + '</td>'
                + '<td>' + catCell + '</td>'
                + '<td>' + escapeHtml(tx.description || '') + '</td>'
                + '<td class="ck-table__col--right"><span class="' + amtClass + '">'
                + sign + ' ' + formatEuro(parseFloat(tx.amount || 0))
                + '</span></td>'
                + '</tr>';
        });

        tbody.innerHTML = rows;
    }

    /**
     * Escapes HTML special characters to prevent XSS when building innerHTML.
     *
     * @param   {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Select helpers ────────────────────────────────────────────────────────

    /**
     * Populates a <select> with entries from an object of { id: {id, name} }.
     * Preserves an existing empty placeholder option.
     *
     * @param {HTMLSelectElement} select
     * @param {Object}            items
     * @param {string}            [labelProp='name']
     */
    function populateSelect(select, items, labelProp) {
        labelProp = labelProp || 'name';
        const placeholder = select.querySelector('option[value=""]');
        select.innerHTML = '';
        if (placeholder) { select.appendChild(placeholder.cloneNode(true)); }

        Object.values(items).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value       = item.id;
            opt.textContent = item[labelProp] || item.name || '';
            select.appendChild(opt);
        });
    }

    /**
     * Sets a select's selected value.
     *
     * @param {HTMLSelectElement} select
     * @param {*}                 value
     */
    function setSelectValue(select, value) {
        select.value = value != null ? String(value) : '';
    }

    // ── Team-Checkboxen (ersetzt nativen select[multiple]) ────────────────────

    /**
     * Befüllt das .ck-multiselect-list#treasuryAccountTeamList mit Checkbox-Items.
     * Wird bei jedem Öffnen des Konto-Modals neu gerendert.
     *
     * @param {Object}   teams       - { id: { id, name } }
     * @param {Array}    selectedIds - IDs der vorausgewählten Teams
     */
    function populateTeamCheckboxes(teams, selectedIds) {
        const selected  = (selectedIds || []).map(String);
        const container = document.getElementById('treasuryAccountTeamList');
        if (! container) { return; }

        container.innerHTML = '';
        const list = Object.values(teams);

        if (list.length === 0) {
            const p = document.createElement('p');
            p.className   = 'ck-muted';
            p.textContent = 'Keine Teams vorhanden.';
            container.appendChild(p);
            return;
        }

        list.forEach(function (team) {
            const label = document.createElement('label');
            label.className = 'ck-multiselect-item';

            const cb      = document.createElement('input');
            cb.type       = 'checkbox';
            cb.name       = 'team_ids[]';
            cb.value      = String(team.id);
            cb.className  = 'ck-multiselect-item__checkbox';
            cb.checked    = selected.indexOf(String(team.id)) !== -1;

            const span         = document.createElement('span');
            span.className     = 'ck-multiselect-item__label';
            span.textContent   = team.name || '';

            label.appendChild(cb);
            label.appendChild(span);
            container.appendChild(label);
        });
    }

    // ── Sichtbarkeits-Toggle (global, via onchange-Attribut im Blade) ─────────

    /**
     * Zeigt/versteckt den Team-Bereich im Konto-Modal.
     * Wird per onchange="treasuryAccountVisibilityChange(this.value)" aufgerufen.
     *
     * @param {string} value - 'public' oder 'team_restricted'
     */
    window.treasuryAccountVisibilityChange = function (value) {
        toggleTeamSection(value);
    };

    // ── Category options filtered by transaction type ─────────────────────────

    /**
     * Rebuilds the category dropdown in the transaction modal
     * to show only categories matching the selected type.
     *
     * @param {string} type - 'income' or 'expense'
     */
    function updateCategoryOptions(type) {
        const cats   = (window.CK_Treasury || {}).categories || {};
        const select = document.querySelector('#treasuryTransactionModal [name="category_id"]');
        if (! select) { return; }

        const filtered = {};
        Object.values(cats).forEach(function (c) {
            if (c.transaction_type === type) { filtered[c.id] = c; }
        });

        const currentVal = select.value;
        populateSelect(select, filtered);
        if (filtered[currentVal]) { select.value = currentVal; }
    }

    // ── Transaction Modal ─────────────────────────────────────────────────────

    /**
     * Opens the transaction modal in create or edit mode.
     *
     * @param {'create'|'edit'} mode
     * @param {number|null}     txId
     */
    function openTransactionModal(mode, txId) {
        const data        = window.CK_Treasury || {};
        const form        = document.getElementById('treasuryTransactionForm');
        const methodField = document.getElementById('treasuryTransactionMethodField');
        if (! form) { return; }

        form.reset();

        const accountSel = form.querySelector('[name="account_id"]');
        if (accountSel) { populateSelect(accountSel, data.accounts || {}); }

        const memberSel = form.querySelector('[name="member_id"]');
        if (memberSel) { populateSelect(memberSel, data.members || {}); }

        const typeField = form.querySelector('[name="type"]');
        if (typeField) {
            typeField.addEventListener('change', function () {
                updateCategoryOptions(this.value);
            });
        }

        if (mode === 'create') {
            form.action           = data.routes.transactionStore;
            methodField.innerHTML = '';
            if (typeField) { typeField.value = 'income'; }
            updateCategoryOptions('income');
            const dateField = form.querySelector('[name="transaction_date"]');
            if (dateField) { dateField.value = new Date().toISOString().slice(0, 10); }
        } else {
            const tx = (data.transactions || {})[txId];
            if (! tx) { return; }

            form.action           = data.routes.transactionUpdate + '/' + tx.id;
            methodField.innerHTML = '<input type="hidden" name="_method" value="PATCH">';

            if (accountSel) { setSelectValue(accountSel, tx.account_id); }
            if (typeField)  { setSelectValue(typeField, tx.type); }
            updateCategoryOptions(tx.type);

            const catSel = form.querySelector('[name="category_id"]');
            if (catSel) { setSelectValue(catSel, tx.category_id); }

            ['amount', 'description', 'transaction_date', 'reference_number'].forEach(function (key) {
                const el = form.querySelector('[name="' + key + '"]');
                if (el) { el.value = tx[key] || ''; }
            });

            if (memberSel) { setSelectValue(memberSel, tx.member_id); }
        }

        ckModalOpen('treasuryTransactionModal');
    }

    // ── Account Modal ─────────────────────────────────────────────────────────

    /**
     * Toggles the team-assignment section visibility using classList (not style).
     *
     * @param {string} visibility - 'public' or 'team_restricted'
     */
    function toggleTeamSection(visibility) {
        const section = document.getElementById('treasuryAccountTeamSection');
        if (! section) { return; }

        if (visibility === 'team_restricted') {
            section.classList.remove('is-hidden');
        } else {
            section.classList.add('is-hidden');
        }
    }

    /**
     * Opens the account modal in create or edit mode.
     *
     * @param {'create'|'edit'} mode
     * @param {number|null}     accountId
     */
    function openAccountModal(mode, accountId) {
        const data        = window.CK_Treasury || {};
        const form        = document.getElementById('treasuryAccountForm');
        const methodField = document.getElementById('treasuryAccountMethodField');
        if (! form) { return; }

        form.reset();

        const parentSel = form.querySelector('[name="parent_id"]');
        if (parentSel) { populateSelect(parentSel, data.parentAccounts || {}); }

        // Team-Checkboxen befüllen – ersetzt den nativen <select multiple>
        populateTeamCheckboxes(data.teams || {}, []);

        // visField nur für setSelectValue im Edit-Modus nötig
        const visField = form.querySelector('[name="visibility"]');
        // toggleTeamSection wird via onchange-Attribut im Blade aufgerufen

        if (mode === 'create') {
            form.action           = data.routes.accountStore;
            methodField.innerHTML = '';
            toggleTeamSection('public');
        } else {
            const account = (data.accounts || {})[accountId];
            if (! account) { return; }

            form.action           = data.routes.accountUpdate + '/' + account.id;
            methodField.innerHTML = '<input type="hidden" name="_method" value="PATCH">';

            form.querySelector('[name="name"]').value        = account.name || '';
            form.querySelector('[name="description"]').value = account.description || '';
            if (parentSel) { setSelectValue(parentSel, account.parent_id); }
            if (visField)  { setSelectValue(visField, account.visibility); }

            // Team-Vorauswahl im Edit-Modus (falls Controller account.team_ids liefert)
            populateTeamCheckboxes(data.teams || {}, account.team_ids || []);
            toggleTeamSection(account.visibility);
        }

        ckModalOpen('treasuryAccountModal');
    }

    // ── Contribution Modal ────────────────────────────────────────────────────

    /**
     * Opens the contribution task assignment modal.
     */
    function openContributionModal() {
        const data = window.CK_Treasury || {};

        // Aufgaben-Select befüllen (data.tasks falls vom Controller geliefert)
        const taskSel = document.querySelector('#treasuryContributionModal [name="task_id"]');
        if (taskSel) { populateSelect(taskSel, data.tasks || {}); }

        const accountSel = document.querySelector('#treasuryContributionModal [name="account_id"]');
        if (accountSel) { populateSelect(accountSel, data.accounts || {}); }

        ckModalOpen('treasuryContributionModal');
    }

    // ── Payment Member Modal ──────────────────────────────────────────────────

    /**
     * Opens the member payment assignment modal for the given task meta ID.
     *
     * @param {number} taskMetaId
     */
    function openPaymentMemberModal(taskMetaId) {
        const data = window.CK_Treasury || {};
        const form = document.getElementById('treasuryPaymentMemberForm');
        if (! form) { return; }

        form.reset();
        form.action = data.routes.paymentsStore + '/' + taskMetaId + '/payments';

        const memberSel = form.querySelector('[name="member_id"]');
        if (memberSel) { populateSelect(memberSel, data.members || {}); }

        ckModalOpen('treasuryPaymentMemberModal');
    }

    // ── Public dispatch ───────────────────────────────────────────────────────

    /**
     * Primary entry point called from Blade templates.
     *
     * @param {'transaction'|'account'|'contribution'|'paymentMember'} type
     * @param {'create'|'edit'} mode
     * @param {number|null}     id
     */
    window.treasuryOpen = function (type, mode, id) {
        switch (type) {
            case 'transaction':   openTransactionModal(mode, id || null); break;
            case 'account':       openAccountModal(mode, id || null);     break;
            case 'contribution':  openContributionModal();                 break;
            case 'paymentMember': openPaymentMemberModal(id);             break;
        }
    };

    // ── Progress bars ─────────────────────────────────────────────────────────

    /**
     * Initialises progress bar widths from data-width attributes on DOMContentLoaded.
     * Width is applied via a CSS data-attribute selector (no el.style.*).
     */
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.ck-progress-bar__fill[data-width]').forEach(function (el) {
            const pct = parseInt(el.getAttribute('data-width'), 10) || 0;
            el.setAttribute('data-width', Math.min(100, Math.max(0, pct)));
        });
    });

}());
