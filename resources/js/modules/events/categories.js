/**
 * Category CRUD handlers:
 *   - New category modal (POST)
 *   - Rename category (PATCH)
 *   - Delete category (DELETE)
 *
 * @param {object} ctx - Shared context { cfg, csrf, closest, reloadKeepingTab }
 */
export function initCategories(ctx) {
    var cfg              = ctx.cfg;
    var csrf             = ctx.csrf;
    var closest          = ctx.closest;
    var reloadKeepingTab = ctx.reloadKeepingTab;

    // ── New Category submit ───────────────────────────────────────────────────

    var newCatBtn = document.getElementById('newCatSubmitBtn');

    if (newCatBtn) {
        newCatBtn.addEventListener('click', function () {
            var nameInput  = document.getElementById('newCatName');
            var name       = nameInput ? nameInput.value.trim() : '';
            var colorInput = document.querySelector('#newCatColorPicker input[type=radio]:checked');
            var color      = colorInput ? colorInput.value : '';

            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            newCatBtn.disabled = true;

            fetch(cfg.routes.categoriesBase, {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ name: name, color: color }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    ckModalClose(null, 'newCatModal');
                    reloadKeepingTab();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Anlegen der Kategorie.');
                    newCatBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                newCatBtn.disabled = false;
            });
        });
    }

    // ── Rename Category ───────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-cat-rename-btn');
        if (! btn) { return; }
        window.ckOpenCatRename(btn.dataset.catId, btn.dataset.catName);
    });

    var renameCatBtn = document.getElementById('renameCatSubmitBtn');

    if (renameCatBtn) {
        renameCatBtn.addEventListener('click', function () {
            if (! window._ckRenameCatId) { return; }

            var nameInput  = document.getElementById('renameCatName');
            var colorInput = document.querySelector('#renameCatColorPicker input[type=radio]:checked');
            var name       = nameInput ? nameInput.value.trim() : '';
            var color      = colorInput ? colorInput.value : '';

            if (! name) {
                if (nameInput) { nameInput.classList.add('ck-input--error'); }
                return;
            }
            if (nameInput) { nameInput.classList.remove('ck-input--error'); }
            renameCatBtn.disabled = true;

            fetch(cfg.routes.categoriesBase + '/' + window._ckRenameCatId, {
                method:  'PATCH',
                headers: {
                    'Content-Type':     'application/json',
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
                body: JSON.stringify({ name: name, color: color }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) {
                    ckModalClose(null, 'renameCatModal');
                    reloadKeepingTab();
                } else {
                    ckNotify('error', data.message || 'Fehler beim Umbenennen der Kategorie.');
                    renameCatBtn.disabled = false;
                }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
                renameCatBtn.disabled = false;
            });
        });
    }

    // ── Delete Category ───────────────────────────────────────────────────────

    document.addEventListener('click', function (e) {
        var btn = closest(e.target, '.ck-cat-delete-btn');
        if (! btn) { return; }

        var catId     = btn.dataset.catId;
        var catName   = btn.dataset.catName;
        var taskCount = parseInt(btn.dataset.taskCount || '0', 10);
        if (! catId) { return; }

        var msg = taskCount > 0
            ? 'Kategorie \u201e' + catName + '\u201c l\u00f6schen? '
                + taskCount + ' Aufgabe(n) werden nach \u201eAllgemein\u201c verschoben.'
            : 'Kategorie \u201e' + catName + '\u201c wirklich l\u00f6schen?';

        window.ckConfirm(msg, function () {
            fetch(cfg.routes.categoriesBase + '/' + catId, {
                method:  'DELETE',
                headers: {
                    'X-CSRF-TOKEN':     csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept':           'application/json',
                },
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.success) { reloadKeepingTab(); }
                else              { ckNotify('error', data.message || 'Fehler beim L\u00f6schen der Kategorie.'); }
            })
            .catch(function () {
                ckNotify('error', 'Netzwerkfehler. Bitte Seite neu laden.');
            });
        });
    });
}
