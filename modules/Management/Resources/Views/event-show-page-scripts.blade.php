{{--
    Management-Hook: JS bridge for events-detail.js (available tasks).
    Extension point: events.show.page.scripts
    Registered by: ManagementServiceProvider

    Data is provided by ManagementServiceProvider::composeEventShowPageScripts() via View Composer.
    No @php blocks or DB queries in this view.

    Variables (injected by composer):
        $mgmtAvailableTasksJs  → array<int, array{id, name, category, priority}>
--}}
<script>
/**
 * Management injects available tasks into window.CK_EventDetail.
 * events-detail.js uses CK_EventDetail.tasks for the add-task dropdown.
 */
window.CK_EventDetail = window.CK_EventDetail || {};
window.CK_EventDetail.tasks = @json($mgmtAvailableTasksJs);
</script>
