{{--
    Fragment: function list content only (no page layout).
    Returned by ManagementController::functionsFragment() for AJAX DOM-swap.
    Renders the Teams hook when available; falls back to the flat table.
--}}
@ckHook('management.function.list')
