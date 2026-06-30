{{--
    Teams-Hook: JS-Bridge und Event-Listener für die Management-Seite.
    Extension point: management.page.scripts
    Registriert von: TeamsServiceProvider

    Aufgaben:
    1. window.CK_Teams aufbauen mit functionTeamIds + taskTeamIds
    2. Auf ck:management.function.modal.open lauschen → Team-Checkboxen vorbefüllen
    3. Auf ck:management.task.modal.open lauschen → Team-Checkboxen vorbefüllen

    Warum hier und nicht in ManagementController?
    Management kennt Teams nicht. Diese Daten werden von Teams injiziert, das die
    Pivot-Tabellen direkt abfragt (management_function_team, management_task_team).

    Data: $ckFunctionTeamIds (array), $ckTaskTeamIds (array)
    — per View Composer aus TeamsServiceProvider::registerViewComposers()
--}}
<script>
window.CK_Teams = window.CK_Teams || {};
window.CK_Teams.functionTeamIds = @json($ckFunctionTeamIds);
window.CK_Teams.taskTeamIds     = @json($ckTaskTeamIds);

/**
 * Vorbefüllt die Team-Checkboxen im Funktions-Modal beim Öffnen im Edit-Modus.
 * Lauscht auf das ck:management.function.modal.open Ereignis (von management-modal.js).
 */
document.addEventListener('ck:management.function.modal.open', function (e) {
    if (!e.detail || e.detail.mode !== 'edit' || !e.detail.functionId) return;

    const teamIds = (window.CK_Teams.functionTeamIds || {})[e.detail.functionId] || [];
    const list    = document.getElementById('mgmtFunctionTeamList');
    if (!list) return;

    list.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.checked = teamIds.indexOf(parseInt(cb.value, 10)) !== -1;
    });
});

/**
 * Vorbefüllt die Team-Checkboxen im Aufgaben-Modal beim Öffnen im Edit-Modus.
 */
document.addEventListener('ck:management.task.modal.open', function (e) {
    if (!e.detail || e.detail.mode !== 'edit' || !e.detail.taskId) return;

    const teamIds = (window.CK_Teams.taskTeamIds || {})[e.detail.taskId] || [];
    const list    = document.getElementById('mgmtTaskTeamList');
    if (!list) return;

    list.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
        cb.checked = teamIds.indexOf(parseInt(cb.value, 10)) !== -1;
    });
});
</script>
