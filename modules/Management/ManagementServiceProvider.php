<?php

declare(strict_types=1);

namespace Modules\Management;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;

/**
 * Bootstraps the Management module.
 *
 * Registers routes, views, migrations and all cross-module hook views.
 *
 * ── Hook architecture ─────────────────────────────────────────────────────────
 *
 * Management extends Events via the hook system.
 * The Events module has NO knowledge of Management — all cross-module wiring
 * runs exclusively through hook views registered here:
 *
 * → Events (index):
 *   event.table.staffing.row     → Assignment cell content (functions + task badges)
 *
 * → Events (show):
 *   events.show.tasks-panel      → Full task panel on the detail page
 *   events.show.page.scripts     → JS data for events-detail.js (CK_EventDetail.tasks)
 *
 * → Admin:
 *   admin.module-settings.sections → Category management in settings
 *
 * ── View Composers ────────────────────────────────────────────────────────────
 *
 * All DB queries needed by event hook-views are handled through View Composers,
 * keeping the view files free of @php blocks and business logic.
 */
class ManagementServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void {}

    /** @return void */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->registerHooks();
        $this->registerViewComposers();
    }

    /** @return void */
    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    /** @return void */
    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'management');
    }

    /**
     * Registers all hook views through which Management extends other modules.
     *
     * @return void
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // Own settings section (Admin)
        $hooks->register('admin.module-settings.sections', 'management::module-settings-section', 30);

        // ── Extend Events ─────────────────────────────────────────────────────

        // Assignment cell in the events list (functions and task badges)
        $hooks->register('event.table.staffing.row', 'management::event-assignments-index-row', 10);

        // Overview tab: KPI tiles + Fortschritt nach Kategorie
        $hooks->register('events.show.overview-panel', 'management::event-overview-panel', 10);

        // Aufgaben tab: task sections by category + add-task select
        $hooks->register('events.show.tasks-panel', 'management::event-tasks-panel', 10);

        // Slots tab: event-day tasks with time-slot ETMs + add-slot form
        $hooks->register('events.show.slots-panel', 'management::event-slots-panel', 10);

        // Funktionen tab: management functions with assigned members
        $hooks->register('events.show.functions-panel', 'management::event-functions-panel', 10);

        // JS data for events-detail.js (available tasks + slot tasks for dropdowns)
        $hooks->register('events.show.page.scripts', 'management::event-show-page-scripts', 10);
    }

    /**
     * Registers View Composers that inject DB-derived data into Management hook-views.
     *
     * All DB queries for event hook-views are centralised here, keeping view files
     * free of @php blocks and business logic. Each composer guards against missing
     * tables (e.g. during first-time module installation).
     *
     * @return void
     */
    private function registerViewComposers(): void
    {
        $this->composeAssignmentsIndexRow();
        $this->composeEventShowPageScripts();
        $this->composeEventOverviewPanel();
        $this->composeEventTasksPanel();
        $this->composeEventEinsatzplanPanel();
        $this->composeEventFunctionsPanel();
    }

    /**
     * View Composer: management::event-assignments-index-row
     *
     * Provides:
     *   $mgmtBesetzungFunctions  → Collection<ManagementFunction>
     *   $mgmtBesetzungTasks      → Collection<ManagementTask>
     *   $mgmtBesetzungHasAny     → bool
     *
     * @return void
     */
    private function composeAssignmentsIndexRow(): void
    {
        View::composer('management::event-assignments-index-row', function ($view) {
            $empty = [
                'mgmtBesetzungFunctions' => collect(),
                'mgmtBesetzungTasks'     => collect(),
                'mgmtBesetzungHasAny'    => false,
            ];

            if (! Schema::hasTable('management_functions') || ! Schema::hasTable('event_management_function')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            $fnIds = DB::table('event_management_function')
                ->where('event_id', $event->id)
                ->pluck('management_function_id')
                ->toArray();

            $taskIds = DB::table('event_task')
                ->where('event_id', $event->id)
                ->pluck('task_id')
                ->toArray();

            $view->with([
                'mgmtBesetzungFunctions' => ! empty($fnIds)
                    ? ManagementFunction::whereIn('id', $fnIds)->orderBy('name')->get()
                    : collect(),
                'mgmtBesetzungTasks' => ! empty($taskIds)
                    ? ManagementTask::whereIn('id', $taskIds)->orderBy('name')->get()
                    : collect(),
                'mgmtBesetzungHasAny' => ! empty($fnIds) || ! empty($taskIds),
            ]);
        });
    }

    /**
     * View Composer: management::event-show-page-scripts
     *
     * Provides:
     *   $mgmtAvailableTasksJs  → array<int, array{id, name, category, priority}>
     *   $mgmtCategoriesJs      → array<int, array{id, name}> for newTaskModal category dropdown
     *   $mgmtEinsatzTasksJs    → array<int, array{id, name}> of event-day tasks for slotModal task dropdown
     *
     * @return void
     */
    private function composeEventShowPageScripts(): void
    {
        View::composer('management::event-show-page-scripts', function ($view) {
            $empty = [
                'mgmtAvailableTasksJs' => [],
                'mgmtCategoriesJs'     => [],
                'mgmtEinsatzTasksJs'   => [],
            ];

            if (! Schema::hasTable('management_tasks')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Available tasks (not yet assigned to this event) for the quick-add dropdown
            $assignedIds = DB::table('event_task')
                ->where('event_id', $event->id)
                ->pluck('task_id')
                ->toArray();

            $availableTasks = ManagementTask::with('category')
                ->whereNotIn('id', $assignedIds)
                ->orderBy('name')
                ->get();

            $mgmtAvailableTasksJs = [];
            foreach ($availableTasks as $task) {
                $mgmtAvailableTasksJs[$task->id] = [
                    'id'       => $task->id,
                    'name'     => $task->name,
                    'category' => $task->category?->name ?? 'Allgemein',
                    'priority' => $task->priority ?? 'normal',
                ];
            }

            // All categories for the newTaskModal category dropdown
            $mgmtCategoriesJs = [];
            if (Schema::hasTable('management_task_categories')) {
                foreach (DB::table('management_task_categories')->orderBy('name')->get() as $cat) {
                    $mgmtCategoriesJs[$cat->id] = ['id' => $cat->id, 'name' => $cat->name];
                }
            }

            // Event-day tasks (assigned to this event) for the slotModal task dropdown
            $mgmtEinsatzTasksJs = [];
            if (Schema::hasTable('event_task')) {
                $eventDate    = $event->starts_at->toDateString();
                $einsatzPivots = DB::table('event_task')
                    ->where('event_id', $event->id)
                    ->where(function ($q) use ($eventDate) {
                        $q->whereNull('deadline_at')
                          ->orWhereDate('deadline_at', '=', $eventDate);
                    })
                    ->pluck('task_id')
                    ->toArray();

                if (! empty($einsatzPivots)) {
                    foreach (ManagementTask::whereIn('id', $einsatzPivots)->orderBy('name')->get() as $task) {
                        $mgmtEinsatzTasksJs[$task->id] = ['id' => $task->id, 'name' => $task->name];
                    }
                }
            }

            $view->with([
                'mgmtAvailableTasksJs' => $mgmtAvailableTasksJs,
                'mgmtCategoriesJs'     => $mgmtCategoriesJs,
                'mgmtEinsatzTasksJs'   => $mgmtEinsatzTasksJs,
            ]);
        });
    }

    /**
     * View Composer: management::event-overview-panel
     *
     * Provides data for the Übersicht tab: 4 KPI tiles + progress per category.
     *
     * Provides:
     *   $mgmtOverviewByCategory → array<string, array{secDone, secTotal}> (same structure as tasks panel)
     *   $mgmtKpiTotalTasks      → int
     *   $mgmtKpiDoneTasks       → int
     *   $mgmtKpiPeopleCount     → int (distinct members, time_from IS NULL)
     *   $mgmtKpiSlotsCount      → int (ETMs with time_from IS NOT NULL)
     *
     * @return void
     */
    private function composeEventOverviewPanel(): void
    {
        View::composer('management::event-overview-panel', function ($view) {
            $empty = [
                'mgmtOverviewByCategory' => [],
                'mgmtKpiTotalTasks'      => 0,
                'mgmtKpiDoneTasks'       => 0,
                'mgmtKpiPeopleCount'     => 0,
                'mgmtKpiSlotsCount'      => 0,
            ];

            if (! Schema::hasTable('management_tasks') || ! Schema::hasTable('event_task')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Load assigned tasks with category and completion state
            $pivots = DB::table('event_task')
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('task_id');

            $assignedIds = $pivots->keys()->toArray();

            if (empty($assignedIds)) {
                $view->with($empty);
                return;
            }

            $assignedTasks = ManagementTask::with('category')
                ->whereIn('id', $assignedIds)
                ->orderBy('name')
                ->get();

            foreach ($assignedTasks as $task) {
                $task->ev_completed = (bool) ($pivots[$task->id]?->completed ?? false);
            }

            // Group by category for progress bars
            $rawByCategory = [];
            foreach ($assignedTasks as $task) {
                $key = $task->category ? $task->category->name : 'Allgemein';
                $rawByCategory[$key][] = $task;
            }
            ksort($rawByCategory);

            $byCategory = [];
            foreach ($rawByCategory as $catName => $catTasks) {
                $done  = (int) collect($catTasks)->where('ev_completed', true)->count();
                $total = count($catTasks);
                $byCategory[$catName] = [
                    'secDone'  => $done,
                    'secTotal' => $total,
                ];
            }

            // KPI counts
            $totalTasks = count($assignedIds);
            $doneTasks  = (int) DB::table('event_task')
                ->where('event_id', $event->id)
                ->where('completed', true)
                ->count();

            $peopleCount = 0;
            $slotsCount  = 0;

            if (Schema::hasTable('event_task_member')) {
                $peopleCount = (int) DB::table('event_task_member')
                    ->where('event_id', $event->id)
                    ->whereNull('time_from')
                    ->distinct('member_id')
                    ->count('member_id');

                $slotsCount = (int) DB::table('event_task_member')
                    ->where('event_id', $event->id)
                    ->whereNotNull('time_from')
                    ->count();
            }

            $view->with([
                'mgmtOverviewByCategory' => $byCategory,
                'mgmtKpiTotalTasks'      => $totalTasks,
                'mgmtKpiDoneTasks'       => $doneTasks,
                'mgmtKpiPeopleCount'     => $peopleCount,
                'mgmtKpiSlotsCount'      => $slotsCount,
            ]);
        });
    }

    /**
     * View Composer: management::event-tasks-panel
     *
     * Provides all data needed for the full task panel on the event detail page:
     *
     *   $mgmtByCategory             → array<string, array{tasks, secDone, secTotal, secColor}>
     *   $mgmtGroupedAvailableTasks  → Collection (grouped by category for <optgroup>)
     *   $mgmtMemberMap              → array<int, list<array{id, member_id, name, time_from, time_to}>>
     *   $mgmtFunctions              → Collection<ManagementFunction>
     *   $mgmtPriorityColors         → array<string, string>
     *   $mgmtPriorityLabels         → array<string, string>
     *
     * @return void
     */
    private function composeEventTasksPanel(): void
    {
        View::composer('management::event-tasks-panel', function ($view) {
            $priorityColors = ['normal' => 'gray', 'important' => 'orange', 'critical' => 'red'];
            $priorityLabels = ['normal' => 'Normal', 'important' => 'Wichtig', 'critical' => 'Kritisch'];

            $empty = [
                'mgmtByCategory'            => [],
                'mgmtGroupedAvailableTasks' => collect(),
                'mgmtMemberMap'             => [],
                'mgmtFunctions'             => collect(),
                'mgmtPriorityColors'        => $priorityColors,
                'mgmtPriorityLabels'        => $priorityLabels,
            ];

            if (! Schema::hasTable('management_tasks') || ! Schema::hasTable('event_task')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // ── Assigned tasks ─────────────────────────────────────────────────
            $pivots = DB::table('event_task')
                ->where('event_id', $event->id)
                ->get()
                ->keyBy('task_id');

            $assignedIds = $pivots->keys()->toArray();

            $assignedTasks = ManagementTask::with('category')
                ->whereIn('id', $assignedIds)
                ->orderBy('name')
                ->get();

            // Attach pivot data to each task as dynamic properties (no Eloquent relation)
            foreach ($assignedTasks as $task) {
                $pivotRow          = $pivots[$task->id] ?? null;
                $task->ev_completed = (bool) ($pivotRow?->completed ?? false);
                $task->ev_notes    = $pivotRow?->notes ?? '';
                $task->ev_deadline = $pivotRow?->deadline_at ?? null;
            }

            // ── Group by category with pre-computed section stats ──────────────
            $rawByCategory  = [];
            $uncategorized  = [];
            foreach ($assignedTasks as $task) {
                if ($task->category) {
                    $rawByCategory[$task->category->name][] = $task;
                } else {
                    $uncategorized[] = $task;
                }
            }
            ksort($rawByCategory);
            if (! empty($uncategorized)) {
                $rawByCategory['Allgemein'] = $uncategorized;
            }

            $byCategory = [];
            foreach ($rawByCategory as $catName => $catTasks) {
                $done  = (int) collect($catTasks)->where('ev_completed', true)->count();
                $total = count($catTasks);
                $byCategory[$catName] = [
                    'tasks'    => $catTasks,
                    'secDone'  => $done,
                    'secTotal' => $total,
                    'secColor' => $done === $total ? 'green' : ($done > 0 ? 'orange' : 'gray'),
                ];
            }

            // ── Member assignments per task (event_task_member) ────────────────
            $memberMap = [];
            if (! empty($assignedIds) && Schema::hasTable('event_task_member')) {
                $etmRows = DB::table('event_task_member')
                    ->join('members', 'members.id', '=', 'event_task_member.member_id')
                    ->whereIn('event_task_member.task_id', $assignedIds)
                    ->where('event_task_member.event_id', $event->id)
                    ->select(
                        'event_task_member.id',
                        'event_task_member.task_id',
                        'event_task_member.member_id',
                        'event_task_member.time_from',
                        'event_task_member.time_to',
                        DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                    )
                    ->get();

                foreach ($etmRows as $etm) {
                    $memberMap[$etm->task_id][] = [
                        'id'        => $etm->id,
                        'member_id' => $etm->member_id,
                        'name'      => $etm->member_name,
                        'time_from' => $etm->time_from ? Carbon::parse($etm->time_from)->format('H:i') : null,
                        'time_to'   => $etm->time_to   ? Carbon::parse($etm->time_to)->format('H:i')   : null,
                    ];
                }
            }

            // ── Available tasks for the add-task dropdown ──────────────────────
            $availableTasks = ManagementTask::with('category')
                ->whereNotIn('id', $assignedIds)
                ->orderBy('name')
                ->get();

            $groupedAvailableTasks = $availableTasks->groupBy(function ($t) {
                return $t->category ? $t->category->name : 'Allgemein';
            });

            // ── Management functions assigned to this event ────────────────────
            $functionIds = Schema::hasTable('event_management_function')
                ? DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->pluck('management_function_id')
                    ->toArray()
                : [];

            $functions = ! empty($functionIds)
                ? ManagementFunction::whereIn('id', $functionIds)->orderBy('name')->get()
                : collect();

            $view->with([
                'mgmtByCategory'            => $byCategory,
                'mgmtGroupedAvailableTasks' => $groupedAvailableTasks,
                'mgmtMemberMap'             => $memberMap,
                'mgmtFunctions'             => $functions,
                'mgmtPriorityColors'        => $priorityColors,
                'mgmtPriorityLabels'        => $priorityLabels,
            ]);
        });
    }

    /**
     * View Composer: management::event-slots-panel
     *
     * Provides data for the Einsatzplan tab (event-day tasks with time-slot ETMs).
     *
     * Provides:
     *   $mgmtEinsatzTasks        → ManagementTask[] (event-day tasks assigned to this event)
     *   $mgmtEinsatzSlotMap      → array<task_id, list<array{id, member_id, name, time_from, time_to}>>
     *   $mgmtEinsatzMembersJs    → array<id, array{id, name}> for member select
     *   $mgmtEinsatzPriorityColors → array<string, string>
     *   $mgmtEinsatzPriorityLabels → array<string, string>
     *
     * @return void
     */
    private function composeEventEinsatzplanPanel(): void
    {
        View::composer('management::event-slots-panel', function ($view) {
            $priorityColors = ['normal' => 'gray', 'important' => 'orange', 'critical' => 'red'];
            $priorityLabels = ['normal' => 'Normal', 'important' => 'Wichtig', 'critical' => 'Kritisch'];

            $empty = [
                'mgmtEinsatzTasks'         => collect(),
                'mgmtEinsatzSlotMap'       => [],
                'mgmtEinsatzMembersJs'     => [],
                'mgmtEinsatzPriorityColors' => $priorityColors,
                'mgmtEinsatzPriorityLabels' => $priorityLabels,
            ];

            if (! Schema::hasTable('management_tasks') || ! Schema::hasTable('event_task')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            $eventDate  = $event->starts_at->toDateString();

            // Event-day tasks: deadline IS NULL or deadline date = event date
            $einsatzPivots = DB::table('event_task')
                ->where('event_id', $event->id)
                ->where(function ($q) use ($eventDate) {
                    $q->whereNull('deadline_at')
                      ->orWhereDate('deadline_at', '=', $eventDate);
                })
                ->get()
                ->keyBy('task_id');

            $einsatzIds = $einsatzPivots->keys()->toArray();

            $einsatzTasks = ! empty($einsatzIds)
                ? ManagementTask::with('category')
                    ->whereIn('id', $einsatzIds)
                    ->orderBy('name')
                    ->get()
                : collect();

            // Attach pivot data (completed, notes) to each task
            foreach ($einsatzTasks as $task) {
                $pivotRow          = $einsatzPivots[$task->id] ?? null;
                $task->ev_completed = (bool) ($pivotRow?->completed ?? false);
                $task->ev_notes    = $pivotRow?->notes ?? '';
            }

            // Time-slot ETMs (time_from IS NOT NULL) for these tasks
            $slotMap = [];
            if (! empty($einsatzIds) && Schema::hasTable('event_task_member')) {
                $slotRows = DB::table('event_task_member')
                    ->join('members', 'members.id', '=', 'event_task_member.member_id')
                    ->where('event_task_member.event_id', $event->id)
                    ->whereIn('event_task_member.task_id', $einsatzIds)
                    ->whereNotNull('event_task_member.time_from')
                    ->orderBy('event_task_member.time_from')
                    ->select(
                        'event_task_member.id',
                        'event_task_member.task_id',
                        'event_task_member.member_id',
                        'event_task_member.time_from',
                        'event_task_member.time_to',
                        DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                    )
                    ->get();

                foreach ($slotRows as $slot) {
                    $slotMap[$slot->task_id][] = [
                        'id'        => $slot->id,
                        'member_id' => $slot->member_id,
                        'name'      => $slot->member_name,
                        'time_from' => Carbon::parse($slot->time_from)->format('H:i'),
                        'time_to'   => Carbon::parse($slot->time_to)->format('H:i'),
                    ];
                }
            }

            // Members for the add-slot form select
            $membersJs = [];
            if (Schema::hasTable('members')) {
                foreach (DB::table('members')
                    ->where('status', 'active')
                    ->whereNull('deleted_at')
                    ->orderBy('last_name')
                    ->select('id', 'first_name', 'last_name')
                    ->get() as $m) {
                    $membersJs[$m->id] = ['id' => $m->id, 'name' => $m->last_name . ', ' . $m->first_name];
                }
            }

            $view->with([
                'mgmtEinsatzTasks'          => $einsatzTasks,
                'mgmtEinsatzSlotMap'        => $slotMap,
                'mgmtEinsatzMembersJs'      => $membersJs,
                'mgmtEinsatzPriorityColors' => $priorityColors,
                'mgmtEinsatzPriorityLabels' => $priorityLabels,
            ]);
        });
    }

    /**
     * View Composer: management::event-functions-panel
     *
     * Provides data for the Funktionen tab (management functions with assigned members).
     *
     * Provides:
     *   $mgmtFuncItems    → array<array{function: ManagementFunction, member: ?object}>
     *
     * @return void
     */
    private function composeEventFunctionsPanel(): void
    {
        View::composer('management::event-functions-panel', function ($view) {
            $empty = ['mgmtFuncItems' => []];

            if (! Schema::hasTable('management_functions')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // All management functions
            $functions = ManagementFunction::orderBy('name')->get();

            // Event-specific overrides (member_id may be null = use global default)
            $eventOverrides = [];
            if (Schema::hasTable('event_management_function')) {
                foreach (DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->get() as $row) {
                    $eventOverrides[$row->management_function_id] = $row->member_id;
                }
            }

            // Global defaults (management_function_member: one default member per function)
            $globalDefaults = [];
            if (Schema::hasTable('management_function_member')) {
                foreach (DB::table('management_function_member')
                    ->get() as $row) {
                    $globalDefaults[$row->management_function_id] = $row->member_id;
                }
            }

            // Resolve effective member IDs and load member records in one query
            $allMemberIds = [];
            foreach ($functions as $fn) {
                $memberId = array_key_exists($fn->id, $eventOverrides)
                    ? $eventOverrides[$fn->id]
                    : ($globalDefaults[$fn->id] ?? null);
                if ($memberId) {
                    $allMemberIds[] = $memberId;
                }
            }

            $memberRecords = [];
            if (! empty($allMemberIds) && Schema::hasTable('members')) {
                foreach (DB::table('members')
                    ->whereIn('id', array_unique($allMemberIds))
                    ->select('id', 'first_name', 'last_name')
                    ->get() as $m) {
                    $memberRecords[$m->id] = $m;
                }
            }

            $items = [];
            foreach ($functions as $fn) {
                $memberId = array_key_exists($fn->id, $eventOverrides)
                    ? $eventOverrides[$fn->id]
                    : ($globalDefaults[$fn->id] ?? null);

                $items[] = [
                    'function'  => $fn,
                    'member'    => $memberId ? ($memberRecords[$memberId] ?? null) : null,
                    'member_id' => $memberId, // raw int for JS pre-select in event-functions-panel
                ];
            }

            $view->with(['mgmtFuncItems' => $items]);
        });
    }
}