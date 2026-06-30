<?php

declare(strict_types=1);

namespace Modules\Teams;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewContract;
use Modules\Teams\Models\Team;

/**
 * Bootstraps the Teams module.
 *
 * Registers routes, views, migrations, all cross-module hook views,
 * and View Composers that supply DB/Eloquent data to hook-view templates.
 *
 * ── Hook architecture ─────────────────────────────────────────────────────────
 *
 * Teams extends Management and Events via the hook system.
 * Both modules have NO knowledge of Teams — all cross-module wiring runs
 * exclusively through hook views registered here:
 *
 * → Management:
 *   management.function.header.filter  → Team filter in the Functions tab header
 *   management.function.list           → Team-grouped function list (replaceable)
 *   management.function.modal.teams    → Team checkboxes in the function modal
 *   management.task.header.filter      → Team filter in the Tasks tab header
 *   management.task.list               → Team-grouped task list (replaceable)
 *   management.task.modal.teams        → Team checkboxes in the task modal
 *   management.page.scripts            → JS bridge (window.CK_Teams) + event listeners
 *
 * → Events:
 *   event.table.teams.header           → <th> Teams column in the event list
 *   event.table.teams.row              → <td> Team badges per row
 *   events.show.teams-panel            → Teams card on the event detail page
 */
class TeamsServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void {}

    /** @return void */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
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
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'teams');
    }

    /** @return void */
    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    /**
     * Registers all hook views through which Teams extends Management and Events.
     *
     * This method is the only place in the Teams module that knows which
     * extension points exist. The target modules (Management, Events) are
     * completely unaware of Teams.
     *
     * @return void
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // ── Extend Management ─────────────────────────────────────────────────

        // Team filter in the Functions tab header
        $hooks->register('management.function.header.filter', 'teams::management-function-header-filter', 10);

        // Team-grouped function list (replaces the flat default list)
        $hooks->register('management.function.list', 'teams::management-function-list', 10);

        // Team checkboxes in the function modal
        $hooks->register('management.function.modal.teams', 'teams::management-function-modal-teams', 10);

        // Team filter in the Tasks tab header
        $hooks->register('management.task.header.filter', 'teams::management-task-header-filter', 10);

        // Team-grouped task list (replaces the flat default list)
        $hooks->register('management.task.list', 'teams::management-task-list', 10);

        // Team checkboxes in the task modal
        $hooks->register('management.task.modal.teams', 'teams::management-task-modal-teams', 10);

        // JS bridge: window.CK_Teams + event listeners for modal pre-fill
        $hooks->register('management.page.scripts', 'teams::management-page-scripts', 10);

        // ── Extend Events ─────────────────────────────────────────────────────

        // <th> Teams column in the event list
        $hooks->register('event.table.teams.header', 'teams::event-teams-index-header', 10);

        // <td> Team badges per row
        $hooks->register('event.table.teams.row', 'teams::event-teams-index-row', 10);

        // Teams card on the event detail page
        $hooks->register('events.show.teams-panel', 'teams::event-show-teams-panel', 10);
    }

    /**
     * Registers View Composers for all Teams hook-views that require DB/Eloquent data.
     *
     * This eliminates @php blocks with Eloquent/DB queries from hook-view templates (4.18),
     * moving data preparation into this service provider where it belongs.
     *
     * Each composer guards against missing tables via Schema::hasTable() so the
     * Teams module can be cleanly installed/uninstalled without fatal errors.
     *
     * @return void
     */
    private function registerViewComposers(): void
    {
        // ── Management: Function header team filter ────────────────────────────
        View::composer('teams::management-function-header-filter', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }
            $view->with([
                'ckFilterTeams' => Team::orderBy('name')->get(),
                'ckFilterValue' => request()->integer('team_id', 0) ?: null,
            ]);
        });

        // ── Management: Function list grouped by team ──────────────────────────
        View::composer('teams::management-function-list', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }

            $functions = $view->getData()['functions'] ?? collect();
            $functions->load('teams');

            $ckTeamFilter = request()->integer('team_id', 0) ?: null;
            $ckDisplay    = $ckTeamFilter
                ? $functions->filter(fn ($f) => $f->teams->contains('id', $ckTeamFilter))
                : $functions;

            $ckGeneral = $ckDisplay->filter(fn ($f) => $f->teams->isEmpty());
            $ckByTeam  = [];
            foreach ($ckDisplay->filter(fn ($f) => $f->teams->isNotEmpty()) as $ckFn) {
                foreach ($ckFn->teams as $ckTeam) {
                    $ckByTeam[$ckTeam->id]['name']        ??= $ckTeam->name;
                    $ckByTeam[$ckTeam->id]['functions'][]   = $ckFn;
                }
            }

            $view->with(compact('ckDisplay', 'ckGeneral', 'ckByTeam'));
        });

        // ── Management: Function modal team checkboxes ─────────────────────────
        View::composer('teams::management-function-modal-teams', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }
            $view->with('ckTeams', Team::orderBy('name')->get());
        });

        // ── Management: Task header team filter ────────────────────────────────
        View::composer('teams::management-task-header-filter', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }
            $view->with([
                'ckFilterTeams' => Team::orderBy('name')->get(),
                'ckFilterValue' => request()->integer('team_id', 0) ?: null,
            ]);
        });

        // ── Management: Task list grouped by team ──────────────────────────────
        View::composer('teams::management-task-list', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }

            $tasks = $view->getData()['tasks'] ?? collect();
            $tasks->load('teams');

            $ckTeamFilter = request()->integer('team_id', 0) ?: null;
            $ckDisplay    = $ckTeamFilter
                ? $tasks->filter(fn ($t) => $t->teams->contains('id', $ckTeamFilter))
                : $tasks;

            $ckGeneral = $ckDisplay->filter(fn ($t) => $t->teams->isEmpty());
            $ckByTeam  = [];
            foreach ($ckDisplay->filter(fn ($t) => $t->teams->isNotEmpty()) as $ckTask) {
                foreach ($ckTask->teams as $ckTeam) {
                    $ckByTeam[$ckTeam->id]['name']    ??= $ckTeam->name;
                    $ckByTeam[$ckTeam->id]['tasks'][]   = $ckTask;
                }
            }

            $view->with(compact('ckDisplay', 'ckGeneral', 'ckByTeam'));
        });

        // ── Management: Task modal team checkboxes ─────────────────────────────
        View::composer('teams::management-task-modal-teams', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }
            $view->with('ckTeams', Team::orderBy('name')->get());
        });

        // ── Management: Page scripts JS data bridge ────────────────────────────
        View::composer('teams::management-page-scripts', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }

            $data      = $view->getData();
            $functions = $data['functions'] ?? collect();
            $tasks     = $data['tasks']     ?? collect();

            $ckFunctionIds = $functions->pluck('id')->toArray();
            $ckFnTeamRows  = DB::table('management_function_team')
                ->whereIn('role_id', $ckFunctionIds)
                ->get()
                ->groupBy('role_id');

            $ckFunctionTeamIds = [];
            foreach ($ckFunctionIds as $ckFnId) {
                $ckFunctionTeamIds[$ckFnId] = $ckFnTeamRows->has($ckFnId)
                    ? $ckFnTeamRows[$ckFnId]->pluck('team_id')->values()->toArray()
                    : [];
            }

            $ckTaskIds      = $tasks->pluck('id')->toArray();
            $ckTaskTeamRows = DB::table('management_task_team')
                ->whereIn('task_id', $ckTaskIds)
                ->get()
                ->groupBy('task_id');

            $ckTaskTeamIds = [];
            foreach ($ckTaskIds as $ckTaskId) {
                $ckTaskTeamIds[$ckTaskId] = $ckTaskTeamRows->has($ckTaskId)
                    ? $ckTaskTeamRows[$ckTaskId]->pluck('team_id')->values()->toArray()
                    : [];
            }

            $view->with(compact('ckFunctionTeamIds', 'ckTaskTeamIds'));
        });

        // ── Events: Event detail page teams panel ──────────────────────────────
        View::composer('teams::event-show-teams-panel', function (ViewContract $view): void {
            if (! Schema::hasTable('teams') || ! Schema::hasTable('event_team')) {
                $view->with('ckShowTeams', collect());
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with('ckShowTeams', collect());
                return;
            }

            $ckShowTeamIds = DB::table('event_team')
                ->where('event_id', $event->id)
                ->pluck('team_id')
                ->toArray();

            $view->with('ckShowTeams', ! empty($ckShowTeamIds)
                ? Team::whereIn('id', $ckShowTeamIds)->orderBy('name')->get()
                : collect());
        });

        // ── Events: Event index row team badges ────────────────────────────────
        View::composer('teams::event-teams-index-row', function (ViewContract $view): void {
            if (! Schema::hasTable('teams') || ! Schema::hasTable('event_team')) {
                $view->with('ckEventTeams', collect());
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with('ckEventTeams', collect());
                return;
            }

            $ckEventTeamIds = DB::table('event_team')
                ->where('event_id', $event->id)
                ->pluck('team_id')
                ->toArray();

            $view->with('ckEventTeams', ! empty($ckEventTeamIds)
                ? Team::whereIn('id', $ckEventTeamIds)->orderBy('name')->get()
                : collect());
        });
    }
}
