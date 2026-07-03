{{--
Management-Hook: Assignment cell content in the events list.
Extension point: event.table.staffing.row
Registered by: ManagementServiceProvider
Data is provided by ManagementServiceProvider::composeAssignmentsIndexRow() via View Composer.
No @php blocks or DB queries in this view.

Variables (injected by composer):
    $mgmtBesetzungFunctions  → Collection<ManagementFunction>
    $mgmtBesetzungTasks      → Collection<ManagementTask>
    $mgmtBesetzungHasAny     → bool
--}}
@if(!$mgmtBesetzungHasAny)
<span class="ck-text-muted">—</span>
@else
@foreach($mgmtBesetzungFunctions as $mgmtBesetzungFn)
<x-ck-badge color="purple">{{ $mgmtBesetzungFn->name }}</x-ck-badge>
@endforeach
@foreach($mgmtBesetzungTasks as $mgmtBesetzungTask)
<x-ck-badge color="amber">{{ $mgmtBesetzungTask->name }}</x-ck-badge>
@endforeach
@endif