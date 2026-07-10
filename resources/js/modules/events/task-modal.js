/**
 * Task modal handlers:
 *   - New task modal (create + import from library)
 *   - Edit task modal (pre-fill + PATCH)
 *   - Remove task
 *   - Select population (category, member dropdowns)
 *
 * @param {object} ctx - Shared context { cfg, csrf, closest, reloadKeepingTab }
 */
export function initTaskModal(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    // ── Mode tracking (null = create, string = taskId for edit) ─────────────
    var _taskEditId         = null;
    var _taskModalOrigTitle = null;

    // ── Modal close: reset to create mode ────────────────────────────────────

    (function () {
        var modal = document.getElementById('newTaskModal');
        if (! modal) { return; }
        var titleEl = modal.querySelector('.ck-modal__title');
        if (titleEl) { _taskModalOrigTitle = titleEl.textContent; }

        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.attributeName !== 'class') { return; }
                if (modal.classList.contains('ck-modal--open')) { return; }
                _taskEditId = null;
                var srcGroup = document.getElementById('newTaskSourceGroup');
                if (srcGroup) { srcGroup.classList.remove('is-hidden'); }
                var t = modal.querySelector('.ck-modal__title');
                if (t && _taskModalOrigTitle) { t.textContent = _taskModalOrigTitle; }
                var nameInput = document.getElementById('newTaskName');
                if (nameInput) { nameInput.value = ''; }
                var deadlineInput = document.getElementById('newTaskDeadline');
                if (deadlineInput) { deadlineInput.value = ''; }
            });
        }).observe(modal, { attributes: true });
    }());

    // ── Populate select dropdowns from CK_EventDetail data ───────────────────

    function populateSelect(selId, dataMap, placeholder) {
        var sel = document.getElementById(selId);
        if (! sel) { return; }
        sel.innerHTML = '';
        var ph         = document.createElement('option');
        ph.value       = '';
        ph.textContent = placeholder;
        sel.appendChild(ph);
        Object.keys(dataMap).forEach(function (id) {
            var opt         = document.createElement('option');
            opt.value       = id;
            opt.textContent = dataMap[id].name;
            sel.appendChild(opt);
        });
    }

    if (cfg.categories) {
        populateSelect('newTaskCategoryId', cfg.categories, '– Keine Kategorie –');
    }
    if (cfg.einsatzplanTasks) {
        populateSelect('slotModalTaskId', cfg.einsatzplanTasks, '– Aufgabe wählen –');
    }
    populateSelect('slotModalMemberId', cfg.members, '– Person wählen –');

    document.querySelectorAll('.ck-func-assign-select').forEach(function (sel) {
        var currentId = sel.dataset.currentMemberId || '';
        Object.keys(cfg.members).forEach(function (id) {
            var opt         = document.createElement('option');
            opt.value       = id;
            opt.textContent = cfg.members[id].name;
            if (id === currentId) { opt.selected = true; }
            sel.appendChild(opt);
        });
    });

    // ── Source dropdown change ────────────────────────────────────────────────

    var newTaskSrcSel = document.getElementById('newTaskSource');
    if (newTaskSrcSel) {
        newTaskSrcSel.addEventListener('change', function () {
            window.ckNewTaskToggleSource(this.value);
        });
    }

    // ── Section "+" button: open modal in CREATE mode ─────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-event-section__add-task-btn');
        if (! btn) { return; }
        e.stopPropagation();
        e.preventDefault();
        _taskEditId = null;
        var modal = document.getElementById('newTaskModal');
        if (modal) {
            var t = modal.querySelector('.ck-modal__title');
            if (t && _taskModalOrigTitle) { t.textContent = _taskModalOrigTitle; }
        }
        var catSel = document.getElementById('newTaskCategoryId');
        if (catSel) { catSel.value = btn.dataset.defaultCatId || ''; }
        ckModalOpen('newTaskModal');
    });

    // ── Edit task: pre-fill modal in EDIT mode ────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-edit-btn');
        if (! btn) { return; }

        _taskEditId = btn.dataset.taskId;

        var nameInput     = document.getElementById('newTaskName');
        var catSelect     = document.getElementById('newTaskCategoryId');
        var prioSelect    = document.getElementById('newTaskPriority');
        var deadlineInput = document.getElementById('newTaskDeadline');

        if (nameInput)     { nameInput.value     = btn.dataset.taskName     || ''; }
        if (prioSelect)    { prioSelect.value    = btn.dataset.taskPriority || 'normal'; }
        if (deadlineInput) { deadlineInput.value = btn.dataset.taskDeadline || ''; }
        if (catSelect)     { catSelect.value     = btn.dataset.taskCatId    || ''; }

        // Edit mode: hide source dropdown (task already exists).
        var srcGroup  = document.getElementById('newTaskSourceGroup');
        var nameGroup = document.getElementById('newTaskNameGroup');
        var prioGroup = document.getElementById('newTaskPriorityGroup');
        if (srcGroup)  { srcGroup.classList.add('is-hidden'); }
        if (nameGroup) { nameGroup.classList.remove('is-hidden'); }
        if (prioGroup) { prioGroup.classList.remove('is-hidden'); }

        var modal = document.getElementById('newTaskModal');
        if (modal) {
            var t = modal.querySelector('.ck-modal__title');
            if (t) { t.textContent = 'Aufgabe bearbeiten'; }
        }

        ckModalOpen('newTaskModal');
    });

    // ── Remove task ───────────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-task-remove-btn');
        if (! btn) { return; }
        var taskId = btn.dataset.taskId;
        if (! taskId) { return; }
        btn.disabled = true;
        fetch(cfg.routes.tasksBase + '/' + taskId, {
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

    // ── Import task from library ──────────────────────────────────────────────

    var importTaskBtn    = document.getElementById('importTaskBtn');
    var importTaskSelect = document.getElementById('importTaskSelect');

    if (importTaskBtn && importTaskSelect) {
        importTaskBtn.addEventListener('click', function () {
            var templateId = importTaskSelect.value;
            if (! templateId) {
                importTaskSelect.classList.add('ck-input--error');
                return;
            }
            importTaskSelect.classList.remove('ck-input--error');
            importTaskBtn.disabled = true;

            fetch(cfg.routes.tasksBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ template_id: parseInt(templateId, 10) }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) { reloadKeepingTab(); }
                else {
                    ckNotify('error', data.message || 'Fehler beim Importieren der Aufgabe.');
                    importTaskBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                importTaskBtn.disabled = false;
            });
        });
    }

    // ── New task submit (create or edit) ──────────────────────────────────────

    var newTaskBtn = document.getElementById('newTaskSubmitBtn');

    if (newTaskBtn) {
        newTaskBtn.addEventListener('click', function () {
            var srcSel        = document.getElementById('newTaskSource');
            var nameInput     = document.getElementById('newTaskName');
            var prioSelect    = document.getElementById('newTaskPriority');
            var deadlineInput = document.getElementById('newTaskDeadline');

            var sourceVal = srcSel ? srcSel.value : 'new';
            var isNew     = (sourceVal === 'new');
            var body      = {};

            if (isNew) {
                var name = nameInput ? nameInput.value.trim() : '';
                if (! name) {
                    if (nameInput) { nameInput.classList.add('ck-input--error'); }
                    return;
                }
                if (nameInput) { nameInput.classList.remove('ck-input--error'); }
                body.name     = name;
                body.priority = (prioSelect && prioSelect.value) ? prioSelect.value : 'normal';
            } else {
                body.template_id = parseInt(sourceVal, 10);
            }

            var catId = window._ckNewTaskCatId || '';
            if (catId !== '' && catId !== 'allgemein') {
                body.category_id = parseInt(catId, 10);
            }

            if (deadlineInput && deadlineInput.value) { body.deadline_at = deadlineInput.value; }

            newTaskBtn.disabled = true;

            var taskUrl    = _taskEditId ? (cfg.routes.tasksBase + '/' + _taskEditId) : cfg.routes.tasksBase;
            var taskMethod = _taskEditId ? 'PATCH' : 'POST';

            fetch(taskUrl, {
                method:  taskMethod,
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify(body),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (! data.success) {
                    ckNotify('error', data.message || 'Fehler beim Speichern der Aufgabe.');
                    newTaskBtn.disabled = false;
                    return;
                }
                _taskEditId = null;
                reloadKeepingTab();
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newTaskBtn.disabled = false;
            });
        });
    }
}