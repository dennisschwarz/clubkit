{{--
    Fragment: task list content only (no page layout).
    Returned by ManagementController::tasksFragment() for AJAX DOM-swap.
    Renders the Teams hook when available; falls back to the flat table.
--}}
@ckHook('management.task.list')
