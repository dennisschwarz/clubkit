/**
 * Task list interactions:
 *   - Task completion checkbox (optimistic UI + PATCH)
 *   - Remove member from task (ETM delete)
 *   - Progress bar + section badge updates
 *   - SortableJS drag & drop reordering within and across categories
 *
 * @param {object} ctx - Shared context { cfg, csrf, Sortable, closest, reloadKeepingTab }
 */
export function initTaskList(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var Sortable         = ctx.Sortable;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Updates the section badge text and colour after a completion toggle.
     *
     * @param {string|Element} sectionSlug
     */
    function updateSectionBadge(sectionSlug) {
        var section = (typeof sectionSlug === 'string')
            ? document.querySelector('[data-section="' + sectionSlug + '"]')
            : sectionSlug;
        if (! section) { return; }

        var done  = section.querySelectorAll('.ck-task-row--done').length;
        var total = section.querySelectorAll('.ck-task-row').length;
        var slug  = (typeof sectionSlug === 'string') ? sectionSlug : null;

        if (slug) {
            var badge = document.querySelector('[data-section-badge="' + slug + '"]');
            if (badge) {
                badge.textContent = done + '/' + total;
                badge.classList.remove('ck-badge--green', 'ck-badge--amber', 'ck-badge--gray');
                if (done === total) {
                    badge.classList.add('ck-badge--green');
                } else if (done > 0) {
                    badge.classList.add('ck-badge--amber');
                } else {
                    badge.classList.add('ck-badge--gray');
                }
            }
        }
    }

    /**
     * Updates every progress indicator that depends on task completion state:
     *   - Hero progress bar (.ck-event-progress__fill)
     *   - Hero done-task counter (#global-done-count)
     *   - Overview KPI cards (#ov-kpi-done, #ov-kpi-open)
     *   - Overview category progress bars (.ck-cat-progress__fill[data-section])
     *   - Overview category counters (.ck-cat-progress__count[data-section])
     *
     * Uses el.style.setProperty() which is the only permitted el.style.* call
     * (needed to set CSS custom property --progress, not achievable via classList).
     */
    function updateAllProgress() {
        var allRows  = document.querySelectorAll('.ck-task-row:not(.ck-task-row--empty)');
        var doneRows = document.querySelectorAll('.ck-task-row--done');
        var total    = allRows.length;
        var done     = doneRows.length;

        // ── Hero bar + counter ────────────────────────────────────────────────
        var counter = document.getElementById('global-done-count');
        if (counter) { counter.textContent = String(done); }

        var heroBar = document.querySelector('.ck-event-progress__fill');
        if (heroBar && total > 0) {
            heroBar.style.setProperty('--progress', Math.round(done / total * 100) + '%');
        }

        // ── Overview KPI tiles ────────────────────────────────────────────────
        var ovDone = document.getElementById('ov-kpi-done');
        var ovOpen = document.getElementById('ov-kpi-open');
        if (ovDone) { ovDone.textContent = String(done); }
        if (ovOpen) { ovOpen.textContent = String(total - done); }

        // ── Overview category progress bars ───────────────────────────────────
        // Each .ck-cat-progress__fill carries data-section matching task-row data-section.
        document.querySelectorAll('.ck-cat-progress__fill[data-section]').forEach(function (fill) {
            var section  = fill.dataset.section;
            var catRows  = document.querySelectorAll('.ck-task-row[data-section="' + section + '"]:not(.ck-task-row--empty)');
            var catDone  = document.querySelectorAll('.ck-task-row--done[data-section="' + section + '"]');
            var catTotal = catRows.length;
            var catDoneN = catDone.length;

            fill.style.setProperty(
                '--progress',
                catTotal > 0 ? Math.round(catDoneN / catTotal * 100) + '%' : '0%'
            );

            // Update the sibling count label.
            var countEl = document.querySelector('.ck-cat-progress__count[data-section="' + section + '"]');
            if (countEl) { countEl.textContent = catDoneN + '/' + catTotal; }
        });
    }

    // ── Initial renders ───────────────────────────────────────────────────────

    updateAllProgress();

    document.querySelectorAll('.ck-cat-progress__fill').forEach(function (fill) {
        fill.style.setProperty('--progress', (fill.dataset.progress || '0') + '%');
    });

    // ── Task checkbox (completion toggle) ─────────────────────────────────────

    document.addEventListener('change', function (e) {
        if (! e.target.matches('.ck-task-checkbox')) { return; }

        var checkbox  = e.target;
        var taskId    = checkbox.dataset.taskId;
        var completed = checkbox.checked;
        var row       = closest(checkbox, '.ck-task-row');
        var section   = row ? row.dataset.section : null;

        if (row) {
            if (completed) {
                row.classList.add('ck-task-row--done');
            } else {
                row.classList.remove('ck-task-row--done');
            }
        }

        if (section) { updateSectionBadge(section); }
        updateAllProgress();

        fetch(cfg.routes.tasksBase + '/' + taskId + '/complete', {
            method:  'PATCH',
            headers: {
                'Content-Type':     'application/json',
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ completed: completed }),
        })
        .then(function (res) {
            if (! res.ok) {
                checkbox.checked = ! completed;
                if (row) {
                    if (! completed) { row.classList.add('ck-task-row--done'); }
                    else             { row.classList.remove('ck-task-row--done'); }
                }
                if (section) { updateSectionBadge(section); }
                updateAllProgress();
            }
        })
        .catch(function () {
            checkbox.checked = ! completed;
        });
    });

    // ── Remove member from task (ETM, task-tab) ───────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-etm-remove-btn');
        if (! btn) { return; }

        var etmId = btn.dataset.etmId;
        if (! etmId) { return; }

        btn.disabled = true;

        fetch(cfg.routes.membersBase + '/' + etmId, {
            method:  'DELETE',
            headers: {
                'X-CSRF-TOKEN':     csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) { reloadKeepingTab(); }
            else              { btn.disabled = false; }
        })
        .catch(function () { btn.disabled = false; });
    });

    // ── SortableJS: drag & drop task reordering ───────────────────────────────
    // Shared group 'event-tasks' allows dragging between category sections.
    // On drop: PATCH tasksBase/{taskId}/move { category_id, sort_order }.

    var sortableOptions = {
        group:       'event-tasks',
        handle:      'td:not(.ck-table__col--actions)',
        animation:   150,
        ghostClass:  'sortable-ghost',
        chosenClass: 'sortable-chosen',

        onEnd: function (evt) {
            var taskRow   = evt.item;
            var taskId    = taskRow.dataset.taskId;
            var fromTbody = evt.from;
            var toTbody   = evt.to;
            var rawCatId  = toTbody.dataset.catId;

            var catId = (rawCatId === 'allgemein' || rawCatId === '')
                ? null
                : parseInt(rawCatId, 10);

            if (! taskId) { return; }

            var toEmpty = toTbody.querySelector('.ck-task-row--empty');
            if (toEmpty) { toEmpty.remove(); }

            if (fromTbody !== toTbody) {
                var realRows = fromTbody.querySelectorAll('.ck-task-row:not(.ck-task-row--empty)');
                if (realRows.length === 0) {
                    var emptyRow = document.createElement('tr');
                    emptyRow.className = 'ck-task-row--empty';
                    emptyRow.innerHTML = '<td colspan="8" class="ck-empty-state">'
                        + (window.CK_EventDetail.i18n && window.CK_EventDetail.i18n.sectionEmpty
                            ? window.CK_EventDetail.i18n.sectionEmpty
                            : 'Noch keine Aufgaben in diesem Bereich.')
                        + '</td>';
                    fromTbody.appendChild(emptyRow);
                }
                updateSectionBadge(fromTbody);
                updateSectionBadge(toTbody);
            }

            fetch(cfg.routes.tasksBase + '/' + taskId + '/move', {
                method:  'PATCH',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({
                    category_id: catId,
                    sort_order:  evt.newIndex,
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (! data.success) {
                    ckNotify('error', 'Fehler beim Verschieben der Aufgabe.');
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler beim Verschieben. Seite neu laden.');
            });
        },
    };

    // Store options globally so ckEvtTab can re-init late-rendered tbodies.
    window._ckSortableOptions = sortableOptions;

    document.querySelectorAll('.ck-task-sortable').forEach(function (tbody) {
        tbody.setAttribute('data-sortable-init', '1');
        Sortable.create(tbody, sortableOptions);
    });
}