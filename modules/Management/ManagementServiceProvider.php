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
 *   event.table.besetzung.row    → Assignment cell content (functions + task badges)
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
        $hooks->register('event.table.besetzung.row', 'management::event-assignments-index-row', 10);

        // Full task panel on the event detail page
        $hooks->register('events.show.tasks-panel', 'management::event-tasks-panel', 10);

        // JS data for events-detail.js (available tasks for dropdown)
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
        $this->composeEventTasksPanel();
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
     *
     * @return void
     */
    private function composeEventShowPageScripts(): void
    {
        View::composer('management::event-show-page-scripts', function ($view) {
            if (! Schema::hasTable('management_tasks')) {
                $view->with(['mgmtAvailableTasksJs' => []]);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with(['mgmtAvailableTasksJs' => []]);
                return;
            }

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

            $view->with(['mgmtAvailableTasksJs' => $mgmtAvailableTasksJs]);
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
}
