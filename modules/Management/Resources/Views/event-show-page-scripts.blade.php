{{--
    Management-Hook: JS bridge for events-detail.js (available tasks + functions).
    Extension point: events.show.page.scripts
    Registered by: ManagementServiceProvider

    Data is provided by ManagementServiceProvider::composeEventShowPageScripts() via View Composer.
    No @php blocks or DB queries in this view.

    Variables (injected by composer):
        $mgmtAvailableTasksJs      → array<int, array{id, name, category, priority}>
        $mgmtCategoriesJs          → array<int, array{id, name}>
        $mgmtShiftTasksJs          → array<int, array{id, name}>

    Variables (injected by composeEventFunctionsPanel() via the functions-panel hook,
    forwarded into CK_EventDetail here so events-detail.js can populate the
    add-function modal select):
        $mgmtAvailableFunctionsJs  → array<int, array{id, name}>
--}}
<script>
/**
 * Management module injects data into window.CK_EventDetail for events-detail.js:
 *   tasks              → available tasks for the quick-add dropdown (not yet imported)
 *   categories         → task categories for the newTaskModal category select
 *   shiftTasks         → event-day tasks for the shift config-modal task select
 *   availableFunctions → global functions not yet assigned to this event
 */
window.CK_EventDetail = window.CK_EventDetail || {};
window.CK_EventDetail.tasks              = @json($mgmtAvailableTasksJs);
window.CK_EventDetail.categories         = @json($mgmtCategoriesJs);
window.CK_EventDetail.shiftTasks         = @json($mgmtShiftTasksJs);
window.CK_EventDetail.availableFunctions = @json($mgmtAvailableFunctionsJs);
window.CK_EventDetail.slotConfig         = @json($mgmtShiftSlotConfigJs);
</script>