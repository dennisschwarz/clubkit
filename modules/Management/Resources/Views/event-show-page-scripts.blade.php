{{--
Management-Hook: JS bridge for events-detail.js (available tasks).
Extension point: events.show.page.scripts
Registered by: ManagementServiceProvider
Data is provided by ManagementServiceProvider::composeEventShowPageScripts() via View Composer.
No @php blocks or DB queries in this view.

Variables (injected by composer):
    $mgmtAvailableTasksJs  → array<int, array{id, name, category, priority}>
    $mgmtCategoriesJs      → array<int, array{id, name}>
    $mgmtEinsatzTasksJs    → array<int, array{id, name}>
--}}
<script>
 /**
  * 
  * Management injects data into window.CK_EventDetail for events-detail.js: 
  * tasks → available tasks for the quick-add dropdown (not yet assigned) 
  * categories → all task categories for the newTaskModal category select 
  * einsatzplanTasks → event-day tasks for the slotModal task select
  */
  window.CK_EventDetail = window.CK_EventDetail || {}; 
  window.CK_EventDetail.tasks = @json($mgmtAvailableTasksJs);
  window.CK_EventDetail.categories = @json($mgmtCategoriesJs);
  window.CK_EventDetail.einsatzplanTasks = @json($mgmtEinsatzTasksJs);
</script>