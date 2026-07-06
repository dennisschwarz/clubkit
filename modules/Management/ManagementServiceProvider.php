<?php

declare(strict_types=1);

namespace Modules\Management;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
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
 *   events.show.page.scripts     → JS data for events-detail.js
 *
 * → Admin:
 *   admin.module-settings.sections → Category management in settings
 *
 * ── View Composers ────────────────────────────────────────────────────────────
 *
 * All DB queries needed by event hook-views are handled through View Composers,
 * keeping the view files free of @php blocks and business logic.
 *
 * ── Schema mapping (after refactor) ──────────────────────────────────────────
 *
 * Old (Events module, deleted):        New (Management module):
 *   event_task (pivot)              →    event_tasks (entity, id + data cols)
 *   event_task_member (pivot)       →    event_task_members (entity, event_task_id FK)
 *
 * Event tasks are now owned by Management. All ViewComposers use:
 *   EventTask::with('category') instead of ManagementTask via event_task pivot.
 *   event_task_members.event_task_id instead of the old (event_id, task_id) composite.
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
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
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

        // Assignment cell in the events list (functions + task name badges)
        $hooks->register('event.table.staffing.row', 'management::event-assignments-index-row', 10);

        // Hero card right column: Vereinsfunktionen summary (name + assigned member)
        $hooks->register('events.show.hero-right', 'management::event-hero-functions', 10);

        // Overview tab: KPI tiles + Wochenplan + staffing matrix + teams
        $hooks->register('events.show.overview-panel', 'management::event-overview-panel', 10);

        // Tasks tab: task sections grouped by event_task_category + member assignments
        $hooks->register('events.show.tasks-panel', 'management::event-tasks-panel', 10);

        // Einsatzplan tab: event-day tasks with time-slot ETMs
        $hooks->register('events.show.slots-panel', 'management::event-slots-panel', 10);

        // Functions tab: management functions with assigned members
        $hooks->register('events.show.functions-panel', 'management::event-functions-panel', 10);

        // JS data bridge for events-detail.js
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
        $this->composeEventHeroFunctions();
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
     *   $mgmtBesetzungTasks      → Collection (stdClass: id, name) from event_tasks
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

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Load event task names (regardless of template origin).
            $eventTasks = collect();
            if (Schema::hasTable('event_tasks')) {
                $eventTasks = DB::table('event_tasks')
                    ->where('event_id', $event->id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }

            // Load functions assigned to this event.
            $fnIds = [];
            if (Schema::hasTable('management_functions') && Schema::hasTable('event_management_function')) {
                $fnIds = DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->pluck('management_function_id')
                    ->toArray();
            }

            $view->with([
                'mgmtBesetzungFunctions' => ! empty($fnIds)
                    ? ManagementFunction::whereIn('id', $fnIds)->orderBy('name')->get()
                    : collect(),
                'mgmtBesetzungTasks'  => $eventTasks,
                'mgmtBesetzungHasAny' => ! empty($fnIds) || $eventTasks->isNotEmpty(),
            ]);
        });
    }

    /**
     * View Composer: management::event-show-page-scripts
     *
     * Provides the JS data bridge consumed by events-detail.js:
     *
     *   $mgmtAvailableTasksJs     → global tasks not yet imported (for "import from library" dropdown)
     *   $mgmtCategoriesJs         → event_task_categories for this event (category dropdown in task form)
     *   $mgmtEinsatzTasksJs       → event-day tasks (for Einsatzplan slot-modal task dropdown)
     *   $mgmtAvailableFunctionsJs → global functions not yet assigned (for add-function modal)
     *
     * @return void
     */
    private function composeEventShowPageScripts(): void
    {
        View::composer('management::event-show-page-scripts', function ($view) {
            $empty = [
                'mgmtAvailableTasksJs'     => [],
                'mgmtCategoriesJs'         => [],
                'mgmtEinsatzTasksJs'       => [],
                'mgmtAvailableFunctionsJs' => [],
            ];

            if (! Schema::hasTable('event_tasks')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Global tasks already imported to this event (by template_id).
            // Used to exclude already-imported tasks from the "import from library" dropdown.
            $importedTemplateIds = DB::table('event_tasks')
                ->where('event_id', $event->id)
                ->whereNotNull('template_id')
                ->pluck('template_id')
                ->toArray();

            // Global tasks available for import (not yet imported to this event).
            $mgmtAvailableTasksJs = [];
            if (Schema::hasTable('management_tasks')) {
                $availableTasks = ManagementTask::with('category')
                    ->whereNotIn('id', $importedTemplateIds)
                    ->orderBy('name')
                    ->get();

                foreach ($availableTasks as $task) {
                    $mgmtAvailableTasksJs[$task->id] = [
                        'id'       => $task->id,
                        'name'     => $task->name,
                        'category' => $task->category?->name ?? '',
                        'priority' => $task->priority ?? 'normal',
                    ];
                }
            }

            // Event-specific categories for the task form category dropdown.
            $mgmtCategoriesJs = [];
            if (Schema::hasTable('event_task_categories')) {
                foreach (DB::table('event_task_categories')
                    ->where('event_id', $event->id)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get() as $cat) {
                    $mgmtCategoriesJs[$cat->id] = [
                        'id'    => $cat->id,
                        'name'  => $cat->name,
                        'color' => $cat->color ?? null,
                    ];
                }
            }

            // Event-day tasks for the Einsatzplan slot-modal task dropdown.
            // Event-day task = deadline_at IS NULL or deadline_at date = event start date.
            $mgmtEinsatzTasksJs = [];
            $eventDate          = $event->starts_at->toDateString();
            $einsatzRows        = DB::table('event_tasks')
                ->where('event_id', $event->id)
                ->where(function ($q) use ($eventDate) {
                    $q->whereNull('deadline_at')
                      ->orWhereDate('deadline_at', '=', $eventDate);
                })
                ->orderBy('name')
                ->select('id', 'name')
                ->get();

            foreach ($einsatzRows as $row) {
                $mgmtEinsatzTasksJs[$row->id] = ['id' => $row->id, 'name' => $row->name];
            }

            // Global functions not yet assigned to this event.
            $mgmtAvailableFunctionsJs = [];
            if (Schema::hasTable('management_functions') && Schema::hasTable('event_management_function')) {
                $assignedFnIds = DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->pluck('management_function_id')
                    ->toArray();

                foreach (ManagementFunction::orderBy('name')->get() as $fn) {
                    if (! in_array($fn->id, $assignedFnIds, true)) {
                        $mgmtAvailableFunctionsJs[$fn->id] = ['id' => $fn->id, 'name' => $fn->name];
                    }
                }
            }

            $view->with([
                'mgmtAvailableTasksJs'     => $mgmtAvailableTasksJs,
                'mgmtCategoriesJs'         => $mgmtCategoriesJs,
                'mgmtEinsatzTasksJs'       => $mgmtEinsatzTasksJs,
                'mgmtAvailableFunctionsJs' => $mgmtAvailableFunctionsJs,
            ]);
        });
    }

    /**
     * View Composer: management::event-overview-panel
     *
     * Provides data for the Übersicht tab: KPI tiles, category progress bars,
     * prep task Wochenplan, event-day staffing matrix, functions and teams.
     *
     * Provides:
     *   $mgmtKpiTotalTasks          → int
     *   $mgmtKpiDoneTasks           → int
     *   $mgmtKpiOpenTasks           → int
     *   $mgmtKpiUnstaffedPrep       → int
     *   $mgmtOverviewByCategory     → array<string, array{secDone, secTotal, unstaffedCount}>
     *   $mgmtOvFunctions            → array<array{name, member_name}>
     *   $mgmtOvTeams                → Collection (stdClass: id, name, color)
     *   $mgmtOvPrepByCategory       → array<string, list<array{name, deadline, priority, completed}>>
     *   $mgmtOvDayTasks             → list<array{id, name, completed}>
     *   $mgmtOvDayMatrix            → array<event_task_id, array<hour, list<array{name, initials}>>>
     *   $mgmtOvHours                → list<string>  (e.g. ["09:00", "10:00"])
     *   $mgmtOvWeekData             → list<array{label, range, days, members}>
     *   $mgmtOvActiveKwIdx          → int
     *   $mgmtOvUnstaffedPrepTasks   → list<string>
     *
     * @return void
     */

    /**
     * Injects the Vereinsfunktionen summary into the hero card right column.
     * View: management::event-hero-functions
     * Hook: events.show.hero-right
     *
     * Provides: $heroFunctions → list<array{name: string, member_name: string|null}>
     *
     * @return void
     */
    private function composeEventHeroFunctions(): void
    {
        View::composer('management::event-hero-functions', function ($view) {
            $data          = $view->getData();
            $event         = $data['event']         ?? null;
            $showFunctions = $data['showFunctions'] ?? false;

            if (! $event || ! $showFunctions || ! Schema::hasTable('event_management_function')) {
                $view->with('heroFunctions', []);
                return;
            }

            // member_id lives directly on event_management_function (migration 2026_07_04_000040)
            // — no management_function_member pivot needed here.
            $functions = DB::table('event_management_function')
                ->join('management_functions',
                    'management_functions.id', '=',
                    'event_management_function.management_function_id')
                ->leftJoin('members',
                    'members.id', '=',
                    'event_management_function.member_id')
                ->where('event_management_function.event_id', '=', $event->id)
                ->select([
                    'management_functions.name',
                    'members.first_name',
                    'members.last_name',
                ])
                ->get();

            $heroFunctions = [];
            foreach ($functions as $row) {
                $heroFunctions[] = [
                    'name'        => $row->name,
                    'member_name' => $row->first_name
                        ? trim($row->first_name . ' ' . $row->last_name)
                        : null,
                ];
            }

            $view->with('heroFunctions', $heroFunctions);
        });
    }

    private function composeEventOverviewPanel(): void
    {
        View::composer('management::event-overview-panel', function ($view) {
            $empty = [
                'mgmtKpiTotalTasks'          => 0,
                'mgmtKpiDoneTasks'           => 0,
                'mgmtKpiOpenTasks'           => 0,
                'mgmtKpiUnstaffedPrep'       => 0,
                'mgmtOverviewByCategory'     => [],
                'mgmtOvFunctions'            => [],
                'mgmtOvTeams'                => collect(),
                'mgmtOvPrepByCategory'       => [],
                'mgmtOvDayTasks'             => [],
                'mgmtOvDayMatrix'            => [],
                'mgmtOvHours'                => [],
                'mgmtOvWeekData'             => [],
                'mgmtOvActiveKwIdx'          => 0,
                'mgmtOvUnstaffedPrepTasks'   => [],
            ];

            if (! Schema::hasTable('event_tasks')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Load all event tasks with their category relation.
            // No pivot: completed, deadline_at and notes are columns on event_tasks itself.
            $assignedTasks = EventTask::with('category')
                ->where('event_id', $event->id)
                ->orderBy('name')
                ->get();

            $assignedIds = $assignedTasks->pluck('id')->toArray();

            // KPI counts — derived from loaded tasks (no extra query).
            $mgmtKpiTotalTasks = count($assignedIds);
            $mgmtKpiDoneTasks  = (int) $assignedTasks->where('completed', true)->count();

            // ETM count per event_task_id — used for unstaffed detection.
            $etmCountByTask = [];
            if (Schema::hasTable('event_task_members') && ! empty($assignedIds)) {
                foreach (
                    DB::table('event_task_members')
                        ->whereIn('event_task_id', $assignedIds)
                        ->select('event_task_id')
                        ->get()
                    as $er
                ) {
                    $etmCountByTask[$er->event_task_id] = ($etmCountByTask[$er->event_task_id] ?? 0) + 1;
                }
            }

            // Group by category for the progress bar section (with unstaffed count).
            $rawByCategory = [];
            foreach ($assignedTasks as $task) {
                $key = $task->category ? $task->category->name : 'Allgemein';
                $rawByCategory[$key][] = $task;
            }
            ksort($rawByCategory);

            $byCategory = [];
            foreach ($rawByCategory as $catName => $catTasks) {
                $done      = (int) collect($catTasks)->where('completed', true)->count();
                $total     = count($catTasks);
                $unstaffed = (int) collect($catTasks)
                    ->filter(fn ($t) => ! $t->completed && ! isset($etmCountByTask[$t->id]))
                    ->count();
                $byCategory[$catName] = [
                    'secDone'        => $done,
                    'secTotal'       => $total,
                    'unstaffedCount' => $unstaffed,
                ];
            }

            // Functions assigned to this event: name + staffing status.
            $mgmtOvFunctions = [];
            if (Schema::hasTable('management_functions') && Schema::hasTable('event_management_function')) {
                $funcRows = DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->get();

                $assignedFnIds = $funcRows->pluck('management_function_id')->toArray();
                $functions = ! empty($assignedFnIds)
                    ? ManagementFunction::whereIn('id', $assignedFnIds)->orderBy('name')->get()->keyBy('id')
                    : collect();

                $memberIds = $funcRows->pluck('member_id')->filter()->unique()->toArray();
                $members   = ! empty($memberIds) && Schema::hasTable('members')
                    ? DB::table('members')
                        ->whereIn('id', $memberIds)
                        ->select('id', 'first_name', 'last_name')
                        ->get()
                        ->keyBy('id')
                    : collect();

                foreach ($funcRows as $row) {
                    $fn = $functions[$row->management_function_id] ?? null;
                    if (! $fn) { continue; }
                    $m = $row->member_id ? ($members[$row->member_id] ?? null) : null;
                    $mgmtOvFunctions[] = [
                        'name'        => $fn->name,
                        'member_name' => $m ? $m->last_name . ', ' . $m->first_name : null,
                    ];
                }
            }

            // Teams assigned to this event (Schema-guarded — Teams module is optional).
            $mgmtOvTeams = collect();
            if (Schema::hasTable('event_team') && Schema::hasTable('teams')) {
                $teamIds = DB::table('event_team')
                    ->where('event_id', $event->id)
                    ->pluck('team_id')
                    ->toArray();
                if (! empty($teamIds)) {
                    $mgmtOvTeams = DB::table('teams')
                        ->whereIn('id', $teamIds)
                        ->orderBy('name')
                        ->select('id', 'name', 'color')
                        ->get();
                }
            }

            // Separate prep tasks (deadline before event date) from event-day tasks.
            $mgmtOvPrepByCategory = [];
            $mgmtOvDayTasks       = [];
            $mgmtOvDayMatrix      = [];
            $mgmtOvHours          = [];
            $prepTaskList         = [];
            $eventDateStr         = $event->starts_at->toDateString();

            foreach ($assignedTasks as $task) {
                // deadline_at is cast to Carbon on EventTask — no Carbon::parse() needed.
                $deadlineAt = $task->deadline_at;

                $isPrep = $deadlineAt !== null
                    && $deadlineAt->toDateString() < $eventDateStr;

                if ($isPrep) {
                    $catName = $task->category ? $task->category->name : 'Allgemein';
                    $mgmtOvPrepByCategory[$catName][] = [
                        'name'      => $task->name,
                        'deadline'  => $deadlineAt->format('d.m.'),
                        'priority'  => $task->priority ?? 'normal',
                        'completed' => $task->completed,
                    ];
                    $prepTaskList[] = [
                        'id'        => $task->id,
                        'name'      => $task->name,
                        'completed' => $task->completed,
                        'priority'  => $task->priority ?? 'normal',
                        'deadline'  => $deadlineAt->toDateString(),
                    ];
                } else {
                    $mgmtOvDayTasks[] = [
                        'id'        => $task->id,
                        'name'      => $task->name,
                        'completed' => $task->completed,
                    ];
                }
            }

            ksort($mgmtOvPrepByCategory);

            // Unstaffed prep task names (for the Zeitplan footer warning).
            $mgmtOvUnstaffedPrepTasks = [];
            foreach ($prepTaskList as $pt) {
                if (! isset($etmCountByTask[$pt['id']])) {
                    $mgmtOvUnstaffedPrepTasks[] = $pt['name'];
                }
            }

            $mgmtKpiUnstaffedPrep = count($mgmtOvUnstaffedPrepTasks);

            // ── Wochenplan: build week × member × day grid ────────────────────────────
            $mgmtOvWeekData    = [];
            $mgmtOvActiveKwIdx = 0;

            if (! empty($prepTaskList) && Schema::hasTable('event_task_members')) {
                $prepTaskIds = array_column($prepTaskList, 'id');

                // ETMs for prep tasks (no time_from = task-tab assignment, not timed slot).
                $prepEtms = DB::table('event_task_members')
                    ->join('members', 'members.id', '=', 'event_task_members.member_id')
                    ->whereIn('event_task_members.event_task_id', $prepTaskIds)
                    ->whereNull('event_task_members.time_from')
                    ->select(
                        'event_task_members.event_task_id',
                        'event_task_members.member_id',
                        'members.last_name',
                        'members.first_name',
                        'members.profile_image'
                    )
                    ->get();

                // Group prep tasks by ISO week key (YYYY-WW).
                $byWeek = [];
                foreach ($prepTaskList as $pt) {
                    $date    = Carbon::parse($pt['deadline']);
                    $weekKey = $date->isoWeekYear() . '-' . str_pad((string) $date->isoWeek(), 2, '0', STR_PAD_LEFT);
                    $byWeek[$weekKey][] = $pt;
                }
                ksort($byWeek);

                $dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
                $kwIndex  = 0;
                foreach ($byWeek as $weekKey => $weekTasks) {
                    $monday = Carbon::parse($weekTasks[0]['deadline'])->startOfWeek(Carbon::MONDAY);

                    $days = [];
                    for ($i = 0; $i < 7; $i++) {
                        $d      = $monday->copy()->addDays($i);
                        $days[] = [
                            'wd'      => $dayNames[$i],
                            'short'   => $d->format('j.n.'),
                            'date'    => $d->toDateString(),
                            'isEvent' => $d->toDateString() === $eventDateStr,
                        ];
                    }

                    $weekTaskIds = array_column($weekTasks, 'id');
                    $weekEtms    = $prepEtms->filter(fn ($e) => in_array($e->event_task_id, $weekTaskIds, true));

                    $memberMap = [];
                    foreach ($weekEtms as $etm) {
                        if (! isset($memberMap[$etm->member_id])) {
                            $memberMap[$etm->member_id] = [
                                'initials' => strtoupper(substr($etm->last_name, 0, 1))
                                            . strtoupper(substr($etm->first_name, 0, 1)),
                                'name'     => $etm->last_name,
                                'photo'    => $etm->profile_image,
                                'done'     => 0,
                                'total'    => 0,
                                'byDate'   => [],
                            ];
                        }
                        foreach ($weekTasks as $wt) {
                            if ($wt['id'] === $etm->event_task_id) {
                                $memberMap[$etm->member_id]['byDate'][$wt['deadline']][] = $wt;
                                $memberMap[$etm->member_id]['total']++;
                                if ($wt['completed']) {
                                    $memberMap[$etm->member_id]['done']++;
                                }
                            }
                        }
                    }

                    $kwNum  = (int) substr($weekKey, 5);
                    $endSun = $monday->copy()->addDays(6);

                    $mgmtOvWeekData[] = [
                        'label'   => 'KW' . $kwNum,
                        'range'   => $monday->format('j.n.') . ' – ' . $endSun->format('j.n.'),
                        'days'    => $days,
                        'members' => array_values($memberMap),
                    ];
                    $kwIndex++;
                }

                $mgmtOvActiveKwIdx = max(0, count($mgmtOvWeekData) - 1);
            }

            // If no prep tasks produced week entries, generate stub weeks so the
            // Wochenplan grid is always rendered with an empty but visible structure.
            if (empty($mgmtOvWeekData)) {
                $stubDayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
                $eventMon     = Carbon::parse($eventDateStr)->startOfWeek(Carbon::MONDAY);
                $startMon     = Carbon::now()->startOfWeek(Carbon::MONDAY);

                if ($startMon->gt($eventMon)) {
                    $startMon = $eventMon->copy();
                } elseif ($startMon->diffInWeeks($eventMon) > 4) {
                    $startMon = $eventMon->copy()->subWeeks(4);
                }

                $cur = $startMon->copy();
                while ($cur->lte($eventMon)) {
                    $stubDays = [];
                    for ($i = 0; $i < 7; $i++) {
                        $d          = $cur->copy()->addDays($i);
                        $stubDays[] = [
                            'wd'      => $stubDayNames[$i],
                            'short'   => $d->format('j.n.'),
                            'date'    => $d->toDateString(),
                            'isEvent' => $d->toDateString() === $eventDateStr,
                        ];
                    }
                    $endSun           = $cur->copy()->addDays(6);
                    $mgmtOvWeekData[] = [
                        'label'   => 'KW' . $cur->isoWeek(),
                        'range'   => $cur->format('j.n.') . ' – ' . $endSun->format('j.n.'),
                        'days'    => $stubDays,
                        'members' => [],
                    ];
                    $cur->addWeek();
                }

                $mgmtOvActiveKwIdx = max(0, count($mgmtOvWeekData) - 1);
            }

            // ── Staffing matrix: event-day tasks × hour columns ───────────────────────
            if (! empty($mgmtOvDayTasks) && Schema::hasTable('event_task_members')) {
                $dayTaskIds = array_column($mgmtOvDayTasks, 'id');

                $daySlots = DB::table('event_task_members')
                    ->join('members', 'members.id', '=', 'event_task_members.member_id')
                    ->whereIn('event_task_members.event_task_id', $dayTaskIds)
                    ->whereNotNull('event_task_members.time_from')
                    ->select(
                        'event_task_members.event_task_id',
                        'event_task_members.time_from',
                        'event_task_members.time_to',
                        'members.last_name',
                        'members.first_name'
                    )
                    ->orderBy('event_task_members.time_from')
                    ->get();

                if ($daySlots->isNotEmpty()) {
                    $minH = (int) Carbon::parse($daySlots->min('time_from'))->format('H');
                    $maxH = (int) Carbon::parse($daySlots->max('time_to'))->format('H');

                    for ($h = $minH; $h <= $maxH; $h++) {
                        $mgmtOvHours[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                    }

                    foreach ($mgmtOvDayTasks as $dt) {
                        $mgmtOvDayMatrix[$dt['id']] = array_fill_keys($mgmtOvHours, []);
                    }

                    foreach ($daySlots as $slot) {
                        $hFrom    = (int) Carbon::parse($slot->time_from)->format('H');
                        $hTo      = (int) Carbon::parse($slot->time_to)->format('H');
                        $initials = strtoupper(substr($slot->last_name, 0, 1))
                                  . strtoupper(substr($slot->first_name, 0, 1));
                        for ($h = $hFrom; $h < $hTo; $h++) {
                            $hKey = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                            if (isset($mgmtOvDayMatrix[$slot->event_task_id][$hKey])) {
                                $mgmtOvDayMatrix[$slot->event_task_id][$hKey][] = [
                                    'name'     => $slot->last_name . ', ' . $slot->first_name,
                                    'initials' => $initials,
                                ];
                            }
                        }
                    }
                }
            }

            // Fallback: if day tasks exist but no ETM slots were found, use event start/end
            // hours so the matrix always renders with a visible column structure.
            if (! empty($mgmtOvDayTasks) && empty($mgmtOvHours)
                && $event->starts_at && $event->ends_at
            ) {
                $startH = (int) $event->starts_at->format('H');
                $endH   = (int) $event->ends_at->format('H');

                for ($h = $startH; $h <= $endH; $h++) {
                    $mgmtOvHours[] = str_pad((string) $h, 2, '0', STR_PAD_LEFT) . ':00';
                }

                foreach ($mgmtOvDayTasks as $dt) {
                    $mgmtOvDayMatrix[$dt['id']] = array_fill_keys($mgmtOvHours, []);
                }
            }

            $view->with([
                'mgmtKpiTotalTasks'          => $mgmtKpiTotalTasks,
                'mgmtKpiDoneTasks'           => $mgmtKpiDoneTasks,
                'mgmtKpiOpenTasks'           => $mgmtKpiTotalTasks - $mgmtKpiDoneTasks,
                'mgmtKpiUnstaffedPrep'       => $mgmtKpiUnstaffedPrep,
                'mgmtOverviewByCategory'     => $byCategory,
                'mgmtOvFunctions'            => $mgmtOvFunctions,
                'mgmtOvTeams'                => $mgmtOvTeams,
                'mgmtOvPrepByCategory'       => $mgmtOvPrepByCategory,
                'mgmtOvDayTasks'             => $mgmtOvDayTasks,
                'mgmtOvDayMatrix'            => $mgmtOvDayMatrix,
                'mgmtOvHours'                => $mgmtOvHours,
                'mgmtOvWeekData'             => $mgmtOvWeekData,
                'mgmtOvActiveKwIdx'          => $mgmtOvActiveKwIdx,
                'mgmtOvUnstaffedPrepTasks'   => $mgmtOvUnstaffedPrepTasks,
            ]);
        });
    }

    /**
     * View Composer: management::event-tasks-panel
     *
     * Provides all data needed for the tasks tab on the event detail page.
     *
     * Provides:
     *   $mgmtByCategory          → array<int|'allgemein', array{category, tasks, secDone, secTotal, secColor}>
     *                               key is category_id (int) or 'allgemein' for uncategorised tasks
     *   $mgmtEventCategories     → Collection<EventTaskCategory> for this event
     *   $mgmtMemberMap           → array<event_task_id, list<array{id, member_id, name}>>
     *                               task-tab assignments only (time_from IS NULL)
     *   $mgmtAvailableGlobalTasks → Collection<ManagementTask> not yet imported to this event
     *   $mgmtPriorityColors      → array<string, string>
     *   $mgmtPriorityLabels      → array<string, string>
     *
     * @return void
     */
    private function composeEventTasksPanel(): void
    {
        View::composer('management::event-tasks-panel', function ($view) {
            $priorityColors = ['normal' => 'gray', 'important' => 'amber', 'critical' => 'red'];
            $priorityLabels = ['normal' => 'Normal', 'important' => 'Wichtig', 'critical' => 'Kritisch'];

            $empty = [
                'mgmtByCategory'           => [],
                'mgmtEventCategories'      => collect(),
                'mgmtMemberMap'            => [],
                'mgmtAvailableGlobalTasks' => collect(),
                'mgmtPriorityColors'       => $priorityColors,
                'mgmtPriorityLabels'       => $priorityLabels,
            ];

            if (! Schema::hasTable('event_tasks')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Load all event tasks with their event-local category, sorted by sort_order.
            $eventTasks = EventTask::with('category')
                ->where('event_id', $event->id)
                ->orderBy('sort_order')
                ->get();

            // Group tasks into category sections; "allgemein" receives uncategorised tasks.
            $rawByCategory = [];
            $uncategorized = [];
            foreach ($eventTasks as $task) {
                if ($task->category) {
                    $catKey = $task->category_id;
                    if (! isset($rawByCategory[$catKey])) {
                        $rawByCategory[$catKey] = ['category' => $task->category, 'tasks' => []];
                    }
                    $rawByCategory[$catKey]['tasks'][] = $task;
                } else {
                    $uncategorized[] = $task;
                }
            }

            // Sort category sections by their user-defined sort_order.
            usort($rawByCategory, fn ($a, $b) => $a['category']->sort_order <=> $b['category']->sort_order);

            $byCategory = [];
            foreach ($rawByCategory as $catData) {
                $cat   = $catData['category'];
                $tasks = $catData['tasks'];
                $done  = (int) collect($tasks)->where('completed', true)->count();
                $total = count($tasks);
                $byCategory[$cat->id] = [
                    'category' => $cat,
                    'tasks'    => $tasks,
                    'secDone'  => $done,
                    'secTotal' => $total,
                    'secColor' => $done === $total && $total > 0 ? 'green' : ($done > 0 ? 'orange' : 'gray'),
                ];
            }

            // "Allgemein" section always appears last — even when empty — so users
            // can always add uncategorised tasks without needing a named category first.
            $done  = (int) collect($uncategorized)->where('completed', true)->count();
            $total = count($uncategorized);
            $byCategory['allgemein'] = [
                'category' => null,
                'tasks'    => $uncategorized,
                'secDone'  => $done,
                'secTotal' => $total,
                'secColor' => $done === $total && $total > 0 ? 'green' : ($done > 0 ? 'orange' : 'gray'),
            ];

            // Member assignments per task (task-tab: no time window — time_from IS NULL).
            $memberMap  = [];
            $taskIds    = $eventTasks->pluck('id')->toArray();

            if (! empty($taskIds) && Schema::hasTable('event_task_members')) {
                $etmRows = DB::table('event_task_members')
                    ->join('members', 'members.id', '=', 'event_task_members.member_id')
                    ->whereIn('event_task_members.event_task_id', $taskIds)
                    ->whereNull('event_task_members.time_from')
                    ->select(
                        'event_task_members.id',
                        'event_task_members.event_task_id',
                        'event_task_members.member_id',
                        DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                    )
                    ->get();

                foreach ($etmRows as $etm) {
                    $memberMap[$etm->event_task_id][] = [
                        'id'        => $etm->id,
                        'member_id' => $etm->member_id,
                        'name'      => $etm->member_name,
                    ];
                }
            }

            // Event task categories for the "add category" and task-move context.
            $eventCategories = Schema::hasTable('event_task_categories')
                ? EventTaskCategory::where('event_id', $event->id)->orderBy('sort_order')->get()
                : collect();

            // Global tasks not yet imported to this event (for "import from library" dropdown).
            $availableGlobalTasks = collect();
            if (Schema::hasTable('management_tasks')) {
                $importedTemplateIds = ! empty($taskIds)
                    ? DB::table('event_tasks')
                        ->where('event_id', $event->id)
                        ->whereNotNull('template_id')
                        ->pluck('template_id')
                        ->toArray()
                    : [];

                $availableGlobalTasks = ManagementTask::with('category')
                    ->whereNotIn('id', $importedTemplateIds)
                    ->orderBy('name')
                    ->get();
            }

            $view->with([
                'mgmtByCategory'           => $byCategory,
                'mgmtEventCategories'      => $eventCategories,
                'mgmtMemberMap'            => $memberMap,
                'mgmtAvailableGlobalTasks' => $availableGlobalTasks,
                'mgmtPriorityColors'       => $priorityColors,
                'mgmtPriorityLabels'       => $priorityLabels,
            ]);
        });
    }

    /**
     * View Composer: management::event-slots-panel
     *
     * Provides data for the Einsatzplan tab (event-day tasks with timed member slots).
     *
     * Provides:
     *   $mgmtEinsatzTasks          → Collection<EventTask> (event-day tasks only)
     *   $mgmtEinsatzSlotMap        → array<event_task_id, list<array{id, member_id, name, time_from, time_to}>>
     *   $mgmtEinsatzMembersJs      → array<id, array{id, name}> for the member select
     *   $mgmtEinsatzPriorityColors → array<string, string>
     *   $mgmtEinsatzPriorityLabels → array<string, string>
     *
     * @return void
     */
    private function composeEventEinsatzplanPanel(): void
    {
        View::composer('management::event-slots-panel', function ($view) {
            $priorityColors = ['normal' => 'gray', 'important' => 'amber', 'critical' => 'red'];
            $priorityLabels = ['normal' => 'Normal', 'important' => 'Wichtig', 'critical' => 'Kritisch'];

            $empty = [
                'mgmtEinsatzTasks'          => collect(),
                'mgmtEinsatzSlotMap'        => [],
                'mgmtEinsatzMembersJs'      => [],
                'mgmtEinsatzPriorityColors' => $priorityColors,
                'mgmtEinsatzPriorityLabels' => $priorityLabels,
            ];

            if (! Schema::hasTable('event_tasks')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            $eventDate = $event->starts_at->toDateString();

            // Event-day tasks: deadline_at IS NULL or deadline_at date = event start date.
            $einsatzTasks = EventTask::with('category')
                ->where('event_id', $event->id)
                ->where(function ($q) use ($eventDate) {
                    $q->whereNull('deadline_at')
                      ->orWhereDate('deadline_at', '=', $eventDate);
                })
                ->orderBy('name')
                ->get();

            // Timed member assignments (time_from IS NOT NULL) for these tasks.
            $slotMap = [];
            if ($einsatzTasks->isNotEmpty() && Schema::hasTable('event_task_members')) {
                $einsatzIds = $einsatzTasks->pluck('id')->toArray();

                $slotRows = DB::table('event_task_members')
                    ->join('members', 'members.id', '=', 'event_task_members.member_id')
                    ->whereIn('event_task_members.event_task_id', $einsatzIds)
                    ->whereNotNull('event_task_members.time_from')
                    ->orderBy('event_task_members.time_from')
                    ->select(
                        'event_task_members.id',
                        'event_task_members.event_task_id',
                        'event_task_members.member_id',
                        'event_task_members.time_from',
                        'event_task_members.time_to',
                        DB::raw("CONCAT(members.last_name, ', ', members.first_name) AS member_name")
                    )
                    ->get();

                foreach ($slotRows as $slot) {
                    $slotMap[$slot->event_task_id][] = [
                        'id'        => $slot->id,
                        'member_id' => $slot->member_id,
                        'name'      => $slot->member_name,
                        'time_from' => Carbon::parse($slot->time_from)->format('H:i'),
                        'time_to'   => Carbon::parse($slot->time_to)->format('H:i'),
                    ];
                }
            }

            // Active members for the add-slot form select.
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
     * Unchanged by the task refactor — functions use their own tables.
     *
     * Provides:
     *   $mgmtFuncItems             → array<array{function, member, member_id}>
     *   $mgmtAvailableFunctionsJs  → array<id, array{id, name}>
     *
     * @return void
     */
    private function composeEventFunctionsPanel(): void
    {
        View::composer('management::event-functions-panel', function ($view) {
            $empty = ['mgmtFuncItems' => [], 'mgmtAvailableFunctionsJs' => []];

            if (! Schema::hasTable('management_functions')) {
                $view->with($empty);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with($empty);
                return;
            }

            // Functions explicitly assigned to this event.
            $eventRows = [];
            if (Schema::hasTable('event_management_function')) {
                foreach (DB::table('event_management_function')
                    ->where('event_id', $event->id)
                    ->get() as $row) {
                    $eventRows[$row->management_function_id] = $row->member_id;
                }
            }

            // All global functions for the "add function" modal (unassigned ones only).
            $allFunctions = ManagementFunction::orderBy('name')->get();
            $availableJs  = [];
            foreach ($allFunctions as $fn) {
                if (! array_key_exists($fn->id, $eventRows)) {
                    $availableJs[$fn->id] = ['id' => $fn->id, 'name' => $fn->name];
                }
            }

            if (empty($eventRows)) {
                $view->with(['mgmtFuncItems' => [], 'mgmtAvailableFunctionsJs' => $availableJs]);
                return;
            }

            $assignedFunctionIds = array_keys($eventRows);
            $assignedFunctions   = ManagementFunction::whereIn('id', $assignedFunctionIds)
                ->orderBy('name')
                ->get();

            $allMemberIds  = array_filter(array_values($eventRows));
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
            foreach ($assignedFunctions as $fn) {
                $memberId = $eventRows[$fn->id] ?? null;
                $items[] = [
                    'function'  => $fn,
                    'member'    => $memberId ? ($memberRecords[$memberId] ?? null) : null,
                    'member_id' => $memberId,
                ];
            }

            $view->with([
                'mgmtFuncItems'            => $items,
                'mgmtAvailableFunctionsJs' => $availableJs,
            ]);
        });
    }
}