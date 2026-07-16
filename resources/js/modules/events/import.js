/**
 * CSV import for event tasks.
 *
 * Reads:  window.CK_Import.routes.import   → POST endpoint
 *         window.CK_Import.routes.template → CSV template download
 *
 * Globals exposed for Blade onclick="...":
 *   ckImportReset()   — resets modal back to upload phase
 *   ckImportSubmit()  — POSTs selected rows as JSON, reloads page
 *
 * Phase switching via CSS class, never via el.style.*:
 *   ck-import-phase--active  → visible (JS adds / removes)
 *   ck-import-drop-zone--dragover → hover highlight (JS adds / removes)
 *   ck-import-error--visible → shows the error paragraph (JS adds / removes)
 */

(function () {
    'use strict';

    /* ── Column aliases ─────────────────────────────────────────────────────── */

    var COL_ALIASES = {
        name:             ['name'],
        category:         ['category', 'kategorie'],
        priority:         ['priority', 'priorität', 'prioritaet'],
        deadline:         ['deadline'],
        notes:            ['notes', 'notizen', 'anmerkungen'],
        slot_start:       ['slot_start', 'slot_start_time', 'start'],
        slot_end:         ['slot_end', 'slot_end_time', 'ende'],
        interval_minutes: ['interval_minutes', 'interval_minuten'],
        capacity:         ['capacity', 'kapazität', 'kapazitaet'],
    };

    var VALID_PRIORITIES = ['normal', 'important', 'critical'];
    var VALID_INTERVALS  = [15, 30, 45, 60, 90, 120];

    var GROUP_GENERAL   = '__general__';
    var GROUP_SLOT      = '__slot__';
    var GROUP_INVALID   = '__invalid__';
    var GROUP_DUPLICATE = '__duplicate__';

    var PRI_LABELS = { normal: 'Normal', important: 'Wichtig', critical: 'Kritisch' };

    /* ── Duplicate lookup map ────────────────────────────────────────────────────
       Built on DOMContentLoaded from window.CK_Import.existingTasks.
       Key format: "<name_lower>|<category_lower>"  — category '' for uncategorised.
    ─────────────────────────────────────────────────────────────────────────── */
    var existingTasksMap = {};

    function buildExistingMap(list) {
        existingTasksMap = {};
        (list || []).forEach(function (t) {
            var key = (t.name || '').toLowerCase().trim()
                    + '|'
                    + (t.category || '').toLowerCase().trim();
            existingTasksMap[key] = true;
        });
    }

    function isDuplicate(name, category) {
        var key = (name || '').toLowerCase().trim()
                + '|'
                + (category || '').toLowerCase().trim();
        return !!existingTasksMap[key];
    }

    /* ── CSV parsing ─────────────────────────────────────────────────────────── */

    function stripBom(text) {
        return text.charCodeAt(0) === 0xFEFF ? text.slice(1) : text;
    }

    function detectDelimiter(line) {
        var commas     = (line.match(/,/g) || []).length;
        var semicolons = (line.match(/;/g) || []).length;
        return semicolons > commas ? ';' : ',';
    }

    function parseLine(line, delim) {
        var fields  = [];
        var field   = '';
        var inQuote = false;
        for (var i = 0; i < line.length; i++) {
            var ch = line[i];
            if (ch === '"') {
                if (inQuote && line[i + 1] === '"') { field += '"'; i++; }
                else                                { inQuote = !inQuote; }
            } else if (ch === delim && !inQuote) {
                fields.push(field.trim());
                field = '';
            } else {
                field += ch;
            }
        }
        fields.push(field.trim());
        return fields;
    }

    function resolveHeader(header) {
        var h = header.toLowerCase().trim();
        for (var key in COL_ALIASES) {
            if (COL_ALIASES[key].indexOf(h) !== -1) { return key; }
        }
        return null;
    }

    function validateRow(raw, lineNum) {
        var errors = [];

        var name     = (raw.name     || '').trim();
        var category = (raw.category || '').trim() || null;
        var priority = (raw.priority || 'normal').trim().toLowerCase();
        var deadline = (raw.deadline || '').trim() || null;
        var notes    = (raw.notes    || '').trim() || null;

        var slotStart   = (raw.slot_start       || '').trim() || null;
        var slotEnd     = (raw.slot_end         || '').trim() || null;
        var intervalRaw = (raw.interval_minutes || '').trim();
        var capacityRaw = (raw.capacity         || '').trim();

        if (!name)          { errors.push('Zeile ' + lineNum + ': name fehlt'); }
        else if (name.length > 200) { errors.push('Zeile ' + lineNum + ': name zu lang'); }

        if (VALID_PRIORITIES.indexOf(priority) === -1) {
            errors.push('Zeile ' + lineNum + ': ungültige Priorität „' + priority + '"');
            priority = 'normal';
        }

        var isSlotTask          = !!(slotStart && slotEnd && intervalRaw);
        var slotIntervalMinutes = null;
        var slotCapacity        = null;

        if (isSlotTask) {
            var iv = parseInt(intervalRaw, 10);
            if (VALID_INTERVALS.indexOf(iv) === -1) {
                errors.push('Zeile ' + lineNum + ': ungültiges Intervall ' + intervalRaw);
            } else {
                slotIntervalMinutes = iv;
            }
            var cap = capacityRaw ? parseInt(capacityRaw, 10) : 1;
            slotCapacity = (!isNaN(cap) && cap >= 1) ? cap : 1;
        }

        /* Duplicate check: only for valid rows (invalid rows are never imported anyway) */
        var status = errors.length > 0 ? 'invalid' : 'ok';
        if (status === 'ok' && isDuplicate(name, category)) {
            status = 'duplicate';
        }

        return {
            name:                 name,
            category:             category,
            priority:             priority,
            deadline:             deadline,
            notes:                notes,
            slot_start_time:      slotStart,
            slot_end_time:        slotEnd,
            slot_interval_minutes: slotIntervalMinutes,
            slot_capacity:        slotCapacity,
            is_slot_task:         isSlotTask,
            status:               status,
            errors:               errors,
            _line:                lineNum,
        };
    }

    function parseCsv(text) {
        text = stripBom(text);
        var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
        while (lines.length > 0 && !lines[lines.length - 1].trim()) { lines.pop(); }
        if (lines.length < 2) { return []; }

        var delim   = detectDelimiter(lines[0]);
        var headers = parseLine(lines[0], delim);
        var colMap  = {};
        for (var h = 0; h < headers.length; h++) {
            var key = resolveHeader(headers[h]);
            if (key) { colMap[h] = key; }
        }

        var rows = [];
        for (var i = 1; i < lines.length; i++) {
            if (!lines[i].trim()) { continue; }
            var fields = parseLine(lines[i], delim);
            var raw = {};
            for (var j = 0; j < headers.length; j++) {
                if (colMap[j]) { raw[colMap[j]] = fields[j] || ''; }
            }
            rows.push(validateRow(raw, i + 1));
        }
        return rows;
    }

    /* ── Group classification ─────────────────────────────────────────────────── */

    function classifyRows(rows) {
        var groupMap   = {};
        var groupOrder = [];

        function ensureGroup(id, label, icon) {
            if (!groupMap[id]) {
                groupMap[id] = { id: id, label: label, icon: icon, rows: [] };
                groupOrder.push(id);
            }
        }

        rows.forEach(function (row) {
            if (row.status === 'invalid') {
                ensureGroup(GROUP_INVALID,   'Fehlerhaft',      '⚠️');
                groupMap[GROUP_INVALID].rows.push(row);
            } else if (row.status === 'duplicate') {
                ensureGroup(GROUP_DUPLICATE, 'Bereits vorhanden', '🔁');
                groupMap[GROUP_DUPLICATE].rows.push(row);
            } else if (row.is_slot_task) {
                ensureGroup(GROUP_SLOT,    'Für Einsatzplan', '🗓');
                groupMap[GROUP_SLOT].rows.push(row);
            } else if (row.category) {
                var catId = 'cat__' + row.category;
                ensureGroup(catId, row.category, '📂');
                groupMap[catId].rows.push(row);
            } else {
                ensureGroup(GROUP_GENERAL, 'Allgemein', '📋');
                groupMap[GROUP_GENERAL].rows.push(row);
            }
        });

        return groupOrder.map(function (id) { return groupMap[id]; });
    }

    /* ── DOM rendering ───────────────────────────────────────────────────────── */

    function buildTaskRow(row) {
        var isDup      = row.status === 'duplicate';
        var isInv      = row.status === 'invalid';
        var isDisabled = isDup || isInv;

        var el       = document.createElement('div');
        el.className = 'ck-task-import-row'
            + (isInv ? ' ck-task-import-row--invalid'   : '')
            + (isDup ? ' ck-task-import-row--duplicate' : '');
        el.dataset.rowStatus = row.status;
        el.dataset.rowJson   = JSON.stringify(row);

        /* ── Left column: checkbox + name input ──────────────────────────── */
        var nameCol       = document.createElement('div');
        nameCol.className = 'ck-task-import-row__name-col';

        var check       = document.createElement('input');
        check.type      = 'checkbox';
        check.className = 'ck-import-row-check';
        check.disabled  = isDisabled;
        check.addEventListener('change', function () { onRowCheckChange(el); });

        var nameInput         = document.createElement('input');
        nameInput.type        = 'text';
        nameInput.className   = 'ck-import-field ck-import-field--name';
        nameInput.value       = row.name || '';
        nameInput.placeholder = '(kein Name)';
        nameInput.addEventListener('input', function () { syncRowData(el); });

        nameCol.appendChild(check);
        nameCol.appendChild(nameInput);
        el.appendChild(nameCol);

        /* ── Right column: stacked flex (main + optional slot) ─────────── */
        var fieldsCol       = document.createElement('div');
        fieldsCol.className = 'ck-task-import-row__fields';

        /* Row 1 (right): priority + deadline + notes + badge + error */
        var rowMain       = document.createElement('div');
        rowMain.className = 'ck-task-import-row__main';

        var priSelect       = document.createElement('select');
        priSelect.className = 'ck-import-field ck-import-field--priority';
        ['normal', 'important', 'critical'].forEach(function (p) {
            var opt         = document.createElement('option');
            opt.value       = p;
            opt.textContent = PRI_LABELS[p];
            if (p === (row.priority || 'normal')) { opt.selected = true; }
            priSelect.appendChild(opt);
        });
        priSelect.addEventListener('change', function () { syncRowData(el); });

        /* Deadline field: differs for slot tasks vs. regular tasks.
           Slot task + 1 event date  → fixed text (cannot change).
           Slot task + N event dates → select dropdown with those dates.
           Regular task              → free date input as before. */
        var eventDates = (window.CK_Import && window.CK_Import.eventDates) || [];
        var dlEl;

        if (row.is_slot_task && eventDates.length === 1) {
            /* Pre-fill deadline with the single event date so syncRowData picks it up. */
            row = Object.assign({}, row, { deadline: eventDates[0].value });
            el.dataset.rowJson = JSON.stringify(row);  /* update stored JSON */
            dlEl               = document.createElement('span');
            dlEl.className     = 'ck-task-import-row__date-fixed';
            dlEl.textContent   = eventDates[0].label;

        } else if (row.is_slot_task && eventDates.length > 1) {
            dlEl           = document.createElement('select');
            dlEl.className = 'ck-import-field ck-import-field--deadline';
            var emptyOpt         = document.createElement('option');
            emptyOpt.value       = '';
            emptyOpt.textContent = '— Datum wählen —';
            dlEl.appendChild(emptyOpt);
            eventDates.forEach(function (d) {
                var opt         = document.createElement('option');
                opt.value       = d.value;
                opt.textContent = d.label;
                if (d.value === (row.deadline || '')) { opt.selected = true; }
                dlEl.appendChild(opt);
            });
            dlEl.addEventListener('change', function () { syncRowData(el); });

        } else {
            dlEl       = document.createElement('input');
            dlEl.type  = 'date';
            dlEl.className = 'ck-import-field ck-import-field--deadline';
            dlEl.value = row.deadline || '';
            dlEl.addEventListener('change', function () { syncRowData(el); });
        }

        var notesInput         = document.createElement('input');
        notesInput.type        = 'text';
        notesInput.className   = 'ck-import-field ck-import-field--notes';
        notesInput.value       = row.notes || '';
        notesInput.placeholder = 'Notizen …';
        notesInput.addEventListener('input', function () { syncRowData(el); });

        rowMain.appendChild(priSelect);
        rowMain.appendChild(dlEl);
        rowMain.appendChild(notesInput);

        /* Error text (invalid rows) */
        if (isInv && row.errors && row.errors.length) {
            var errSpan         = document.createElement('span');
            errSpan.className   = 'ck-task-import-row__error';
            errSpan.textContent = row.errors.join(' | ');
            rowMain.appendChild(errSpan);
        }

        /* Badges — both always in DOM, visibility toggled via is-hidden.
           Reuses .ck-badge from badges.css (no custom badge CSS needed). */
        var newBadge       = document.createElement('span');
        newBadge.className = 'ck-badge ck-badge--green';
        newBadge.setAttribute('data-badge', 'new');
        newBadge.textContent = 'Neu';
        if (row.status !== 'ok') { newBadge.classList.add('is-hidden'); }
        rowMain.appendChild(newBadge);

        var dupBadge       = document.createElement('span');
        dupBadge.className = 'ck-badge ck-badge--amber';
        dupBadge.setAttribute('data-badge', 'duplicate');
        dupBadge.textContent = 'Duplikat';
        if (row.status !== 'duplicate') { dupBadge.classList.add('is-hidden'); }
        rowMain.appendChild(dupBadge);

        fieldsCol.appendChild(rowMain);
        el.appendChild(fieldsCol);

        /* ── Row 2 (slot): start – end · interval · capacity ────────────── */
        /* Only rendered for tasks in the Einsatzplan group (is_slot_task).   */
        if (row.is_slot_task) {
            var rowSlot       = document.createElement('div');
            rowSlot.className = 'ck-task-import-row__slot';

            function mkLabel(text) {
                var s = document.createElement('span');
                s.className   = 'ck-task-import-row__slot-label';
                s.textContent = text;
                return s;
            }
            function mkSep(text) {
                var s = document.createElement('span');
                s.className   = 'ck-task-import-row__slot-sep';
                s.textContent = text;
                return s;
            }

            var startInput       = document.createElement('input');
            startInput.type      = 'time';
            startInput.className = 'ck-import-field ck-import-field--time';
            startInput.value     = row.slot_start_time || '';
            startInput.addEventListener('change', function () { syncRowData(el); });

            var endInput       = document.createElement('input');
            endInput.type      = 'time';
            endInput.className = 'ck-import-field ck-import-field--time';
            endInput.value     = row.slot_end_time || '';
            endInput.addEventListener('change', function () { syncRowData(el); });

            var ivSelect       = document.createElement('select');
            ivSelect.className = 'ck-import-field ck-import-field--interval';
            VALID_INTERVALS.forEach(function (iv) {
                var opt         = document.createElement('option');
                opt.value       = String(iv);
                opt.textContent = iv + ' min';
                if (iv === row.slot_interval_minutes) { opt.selected = true; }
                ivSelect.appendChild(opt);
            });
            ivSelect.addEventListener('change', function () { syncRowData(el); });

            var capInput         = document.createElement('input');
            capInput.type        = 'number';
            capInput.className   = 'ck-import-field ck-import-field--capacity';
            capInput.value       = row.slot_capacity || 1;
            capInput.min         = '1';
            capInput.placeholder = '1';
            capInput.title       = 'Kapazität (Personen)';
            capInput.addEventListener('change', function () { syncRowData(el); });

            rowSlot.appendChild(mkLabel('Von'));
            rowSlot.appendChild(startInput);
            rowSlot.appendChild(mkSep('–'));
            rowSlot.appendChild(mkLabel('Bis'));
            rowSlot.appendChild(endInput);
            rowSlot.appendChild(mkSep('·'));
            rowSlot.appendChild(mkLabel('Interval'));
            rowSlot.appendChild(ivSelect);
            rowSlot.appendChild(mkSep('·'));
            rowSlot.appendChild(mkLabel('Kapazität'));
            rowSlot.appendChild(capInput);

            fieldsCol.appendChild(rowSlot);
        }

        return el;
    }

    /**
     * Re-reads all editable inputs from a row, re-validates, updates
     * dataset.rowJson and CSS classes (ok / invalid / duplicate).
     *
     * @param {HTMLElement} rowEl
     */
    function syncRowData(rowEl) {
        var current;
        try { current = JSON.parse(rowEl.dataset.rowJson); } catch (e) { return; }

        var nameInput  = rowEl.querySelector('.ck-import-field--name');
        var priSelect  = rowEl.querySelector('.ck-import-field--priority');
        var dlInput    = rowEl.querySelector('.ck-import-field--deadline');
        var notesInput = rowEl.querySelector('.ck-import-field--notes');
        var startInput = rowEl.querySelector('.ck-import-field--time:first-of-type');
        var endInput   = rowEl.querySelector('.ck-import-field--time:last-of-type');
        var ivSelect   = rowEl.querySelector('.ck-import-field--interval');
        var capInput   = rowEl.querySelector('.ck-import-field--capacity');

        /* Category is determined by the group the row belongs to (Drag & Drop). */
        /* We preserve the current value from rowJson — no category input exists. */
        var raw = {
            name:             nameInput  ? nameInput.value  : (current.name || ''),
            priority:         priSelect  ? priSelect.value  : (current.priority || 'normal'),
            deadline:         dlInput    ? dlInput.value    : (current.deadline || ''),
            category:         current.category || '',
            notes:            notesInput ? notesInput.value : (current.notes || ''),
            slot_start:       startInput ? startInput.value : (current.slot_start_time  || ''),
            slot_end:         endInput   ? endInput.value   : (current.slot_end_time    || ''),
            interval_minutes: ivSelect   ? ivSelect.value   : (current.slot_interval_minutes
                                                                ? String(current.slot_interval_minutes) : ''),
            capacity:         capInput   ? capInput.value   : (current.slot_capacity
                                                                ? String(current.slot_capacity) : ''),
        };

        var updated = validateRow(raw, current._line);

        rowEl.dataset.rowJson   = JSON.stringify(updated);
        rowEl.dataset.rowStatus = updated.status;

        /* CSS classes */
        rowEl.classList.remove('ck-task-import-row--invalid', 'ck-task-import-row--duplicate');
        if (updated.status === 'invalid')   { rowEl.classList.add('ck-task-import-row--invalid'); }
        if (updated.status === 'duplicate') { rowEl.classList.add('ck-task-import-row--duplicate'); }

        /* Badges (ck-badge reuse — toggled via is-hidden) */
        var newBadgeEl = rowEl.querySelector('[data-badge="new"]');
        var dupBadgeEl = rowEl.querySelector('[data-badge="duplicate"]');
        if (newBadgeEl) { newBadgeEl.classList.toggle('is-hidden', updated.status !== 'ok'); }
        if (dupBadgeEl) { dupBadgeEl.classList.toggle('is-hidden', updated.status !== 'duplicate'); }

        /* Error text */
        var errSpan = rowEl.querySelector('.ck-task-import-row__error');
        if (errSpan) { errSpan.textContent = (updated.errors || []).join(' | '); }

        /* Checkbox */
        var check = rowEl.querySelector('.ck-import-row-check');
        if (check) {
            var wasChecked = check.checked;
            check.disabled = updated.status !== 'ok';
            if (check.disabled && wasChecked) {
                check.checked = false;
                var body = rowEl.closest('.ck-import-group-body');
                if (body) { refreshGroup(body.dataset.groupId); }
            }
        }

        updateSelectedCount();
        updateSubmitBtn();
    }

    function buildGroupCard(group) {
        var isInvalid = group.id === GROUP_INVALID || group.id === GROUP_DUPLICATE;

        var card             = document.createElement('div');
        card.className       = 'ck-import-group';
        card.dataset.groupId = group.id;

        /* Header */
        var header       = document.createElement('div');
        header.className = 'ck-import-group-header';

        var hCheck        = document.createElement('input');
        hCheck.type       = 'checkbox';
        hCheck.className  = 'ck-import-group-check';
        hCheck.disabled   = isInvalid;
        hCheck.addEventListener('change', function () {
            onGroupCheckChange(group.id, hCheck.checked);
        });

        var titleSpan         = document.createElement('span');
        titleSpan.className   = 'ck-import-group-title';
        titleSpan.textContent = group.icon + ' ' + group.label;

        var countSpan                    = document.createElement('span');
        countSpan.className              = 'ck-import-group-count';
        countSpan.dataset.groupCount     = group.id;
        countSpan.textContent            = '0/' + group.rows.length;

        header.appendChild(hCheck);
        header.appendChild(titleSpan);
        header.appendChild(countSpan);
        card.appendChild(header);

        /* Body: sortable task rows */
        var body             = document.createElement('div');
        body.className       = 'ck-import-group-body';
        body.dataset.groupId = group.id;

        group.rows.forEach(function (row) { body.appendChild(buildTaskRow(row)); });

        card.appendChild(body);
        return card;
    }

    function renderPreview(rows, groups) {
        /* Summary bar */
        var valid   = rows.filter(function (r) { return r.status === 'ok'; }).length;
        var dups    = rows.filter(function (r) { return r.status === 'duplicate'; }).length;
        var invalid = rows.filter(function (r) { return r.status === 'invalid'; }).length;
        var sumEl   = document.getElementById('ck-import-summary-text');
        if (sumEl) {
            var parts = [valid + ' gültige Task(s)'];
            if (dups    > 0) { parts.push(dups    + ' bereits vorhanden'); }
            if (invalid > 0) { parts.push(invalid + ' fehlerhaft'); }
            sumEl.textContent = parts.join(' · ');
        }

        /* Groups */
        var container = document.getElementById('ck-import-groups');
        if (!container) { return; }
        container.innerHTML = '';
        groups.forEach(function (g) { container.appendChild(buildGroupCard(g)); });

        updateSelectedCount();
    }

    /* ── Checkbox logic ──────────────────────────────────────────────────────── */

    function onGroupCheckChange(groupId, checked) {
        var body = document.querySelector('.ck-import-group-body[data-group-id="' + groupId + '"]');
        if (!body) { return; }
        body.querySelectorAll('.ck-import-row-check:not(:disabled)').forEach(function (c) {
            c.checked = checked;
        });
        refreshGroup(groupId);
        updateSelectedCount();
        updateSubmitBtn();
    }

    function onRowCheckChange(rowEl) {
        var body = rowEl.closest('.ck-import-group-body');
        if (!body) { return; }
        refreshGroup(body.dataset.groupId);
        updateSelectedCount();
        updateSubmitBtn();
    }

    function refreshGroup(groupId) {
        /* Update header checkbox state (checked / indeterminate / unchecked) */
        var card = document.querySelector('.ck-import-group[data-group-id="' + groupId + '"]');
        if (!card) { return; }

        var hCheck = card.querySelector('.ck-import-group-check');
        var body   = card.querySelector('.ck-import-group-body');
        if (!hCheck || !body) { return; }

        var all     = Array.from(body.querySelectorAll('.ck-import-row-check:not(:disabled)'));
        var checked = all.filter(function (c) { return c.checked; });

        if (checked.length === 0) {
            hCheck.checked       = false;
            hCheck.indeterminate = false;
        } else if (checked.length === all.length) {
            hCheck.checked       = true;
            hCheck.indeterminate = false;
        } else {
            hCheck.checked       = false;
            hCheck.indeterminate = true;
        }

        /* Update N/M count */
        var countEl = card.querySelector('[data-group-count="' + groupId + '"]');
        if (countEl) {
            countEl.textContent = checked.length + '/' + all.length;
        }
    }

    function updateSelectedCount() {
        var all     = document.querySelectorAll('#ck-import-groups .ck-import-row-check:not(:disabled)');
        var checked = document.querySelectorAll('#ck-import-groups .ck-import-row-check:checked:not(:disabled)');
        var el      = document.getElementById('ck-import-selected-count');
        if (el) { el.textContent = checked.length + ' von ' + all.length + ' ausgewählt'; }
    }

    function updateSubmitBtn() {
        var btn     = document.getElementById('ck-import-submit-btn');
        var checked = document.querySelectorAll('#ck-import-groups .ck-import-row-check:checked:not(:disabled)');
        if (btn) { btn.disabled = checked.length === 0; }
    }

    /* ── SortableJS drag & drop ──────────────────────────────────────────────── */

    function initSortable() {
        if (!window.Sortable) { return; }

        document.querySelectorAll(
            '.ck-import-group-body:not([data-group-id="' + GROUP_INVALID + '"])'
        ).forEach(function (body) {
            window.Sortable.create(body, {
                group:       'ck-import-rows',
                animation:   150,
                ghostClass:  'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function (evt) {
                    var from = evt.from.dataset.groupId;
                    var to   = evt.to.dataset.groupId;
                    if (from !== to) {
                        refreshGroup(from);
                        refreshGroup(to);
                        updateSelectedCount();
                    }
                },
            });
        });
    }

    /* ── Phase switching ─────────────────────────────────────────────────────── */

    function showPhase(activeId, inactiveId) {
        var active   = document.getElementById(activeId);
        var inactive = document.getElementById(inactiveId);
        if (inactive) { inactive.classList.remove('ck-import-phase--active'); }
        if (active)   { active.classList.add('ck-import-phase--active'); }
    }

    function showPreviewPhase(rows) {
        var groups = classifyRows(rows);
        renderPreview(rows, groups);
        initSortable();
        showPhase('ck-import-preview-phase', 'ck-import-upload-phase');
    }

    window.ckImportReset = function () {
        showPhase('ck-import-upload-phase', 'ck-import-preview-phase');

        var fileInput = document.getElementById('ck-import-file-input');
        if (fileInput) { fileInput.value = ''; }

        hideError();

        var container = document.getElementById('ck-import-groups');
        if (container) { container.innerHTML = ''; }

        updateSubmitBtn();
    };

    /* ── Submit busy-state helpers ───────────────────────────────────────────
       Lock the import modal during the POST request so the user cannot close
       it accidentally via backdrop click or the × button while tasks are being
       saved.  Uses the .ck-modal--locked flag (checked in app.js ckModalClose)
       and .ck-modal-content--busy (CSS spinner + pointer-events:none overlay,
       defined in modals.css).
    ─────────────────────────────────────────────────────────────────────── */

    function _importLock() {
        var overlay = document.getElementById('ckImportModal');
        var content = overlay && overlay.querySelector('.ck-modal-content');
        if (overlay) { overlay.classList.add('ck-modal--locked'); }
        if (content) { content.classList.add('ck-modal-content--busy'); }
    }

    function _importUnlock() {
        var overlay = document.getElementById('ckImportModal');
        var content = overlay && overlay.querySelector('.ck-modal-content');
        if (overlay) { overlay.classList.remove('ck-modal--locked'); }
        if (content) { content.classList.remove('ck-modal-content--busy'); }
    }

    /* ── Submit ──────────────────────────────────────────────────────────────── */

    window.ckImportSubmit = function () {
        var cfg = window.CK_Import;
        if (!cfg) { return; }

        var checkedEls = Array.from(
            document.querySelectorAll('#ck-import-groups .ck-import-row-check:checked:not(:disabled)')
        );
        var tasks = checkedEls
            .map(function (ch) {
                try { return JSON.parse(ch.closest('.ck-task-import-row').dataset.rowJson); }
                catch (e) { return null; }
            })
            .filter(Boolean);

        if (!tasks.length) { return; }

        var btn = document.getElementById('ck-import-submit-btn');
        if (btn) { btn.disabled = true; }

        /* Lock modal: show spinner, block backdrop click and close button. */
        _importLock();

        /* CSRF: prefer CK_EventDetail.csrf, fallback to meta tag */
        var csrf = (window.CK_EventDetail && window.CK_EventDetail.csrf)
            ? window.CK_EventDetail.csrf
            : ((document.querySelector('meta[name="csrf-token"]') || {}).content || '');

        fetch(cfg.routes.import, {
            method:  'POST',
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ tasks: tasks }),
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            /* Page reloads — no need to unlock, but clean up for safety. */
            _importUnlock();
            ckModalClose(null, 'ckImportModal');
            window.ckImportReset();
            var msg = data.imported + ' Task(s) importiert.';
            if (data.skipped > 0) { msg += ' (' + data.skipped + ' übersprungen)'; }
            ckNotify('success', msg);
            /* Reload and keep tasks tab active */
            sessionStorage.setItem('ck_evt_active_tab', 'tasks');
            window.location.reload();
        })
        .catch(function () {
            /* Unlock on error so the user can retry or close the modal. */
            _importUnlock();
            if (btn) { btn.disabled = false; }
            ckNotify('error', 'Fehler beim Importieren. Bitte versuche es erneut.');
        });
    };

    /* ── Error helpers ───────────────────────────────────────────────────────── */

    function showError(msg) {
        var el = document.getElementById('ck-import-upload-error');
        if (el) {
            el.textContent = msg;
            el.classList.add('ck-import-error--visible');
        }
    }

    function hideError() {
        var el = document.getElementById('ck-import-upload-error');
        if (el) {
            el.textContent = '';
            el.classList.remove('ck-import-error--visible');
        }
    }

    /* ── File handling ───────────────────────────────────────────────────────── */

    function handleFile(file) {
        if (!file) { return; }

        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext !== 'csv' && ext !== 'txt') {
            showError('Bitte eine CSV-Datei auswählen (.csv oder .txt).');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showError('Die Datei ist zu groß (max. 2 MB).');
            return;
        }

        hideError();

        var reader = new FileReader();
        reader.onload = function (e) {
            try {
                var rows = parseCsv(e.target.result);
                if (!rows.length) {
                    showError('Die CSV enthält keine Datenzeilen (Kopfzeile vorhanden?).');
                    return;
                }
                showPreviewPhase(rows);
            } catch (err) {
                showError('Fehler beim Lesen der CSV-Datei.');
            }
        };
        reader.onerror = function () { showError('Die Datei konnte nicht gelesen werden.'); };
        reader.readAsText(file, 'UTF-8');
    }

    /* ── Init ────────────────────────────────────────────────────────────────── */

    // When bundled as an ES module via events-detail.js, DOMContentLoaded has
    // already fired before this code runs. Use readyState to handle both cases.
    function initImportModule() {
        var cfg = window.CK_Import;
        if (!cfg) { return; }  /* Module not active on this page */

        /* Build duplicate lookup map from existing event tasks */
        buildExistingMap(cfg.existingTasks || []);

        /* Set template download href */
        var tmplLink = document.getElementById('ck-import-template-link');
        if (tmplLink && cfg.routes && cfg.routes.template) {
            tmplLink.href = cfg.routes.template;
        }

        /* File input change */
        var fileInput = document.getElementById('ck-import-file-input');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                handleFile(this.files[0]);
            });
        }

        /* Drag & drop on label / drop zone */
        var dropZone = document.getElementById('ck-import-drop-zone');
        if (dropZone) {
            dropZone.addEventListener('dragover', function (e) {
                e.preventDefault();
                dropZone.classList.add('ck-import-drop-zone--dragover');
            });
            ['dragleave', 'dragend'].forEach(function (ev) {
                dropZone.addEventListener(ev, function () {
                    dropZone.classList.remove('ck-import-drop-zone--dragover');
                });
            });
            dropZone.addEventListener('drop', function (e) {
                e.preventDefault();
                dropZone.classList.remove('ck-import-drop-zone--dragover');
                handleFile(e.dataTransfer && e.dataTransfer.files[0]);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initImportModule);
    } else {
        initImportModule();
    }

}());