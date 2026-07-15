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
 * Teams extends Members, Management and Events via the hook system.
 * Target modules have NO knowledge of Teams — all cross-module wiring runs
 * exclusively through hook views registered here.
 *
 * → Members:
 *   member.table.header    → <th> Teams column in the members list
 *   member.table.row       → <td> Team badges per member row ($member in scope)
 *   member.modal.tabs      → "Teams" tab button in the member modal
 *   member.modal.sections  → Teams tab content (checkboxes, AJAX save)
 *   member.page.scripts    → JS bridge (CK_MemberTeamsBridge) + member-teams.js
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

    /**
     * Registers all hook views through which Teams extends other modules.
     *
     * @return void
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // ── Extend Members ────────────────────────────────────────────────────

        // <th> Teams column in the members list table
        $hooks->register('member.table.header', 'teams::member-table-header', 15);

        // <td> Team badges per row ($member variable is in Blade scope)
        $hooks->register('member.table.row', 'teams::member-table-row', 15);

        // "Teams" tab button in the member modal
        $hooks->register('member.modal.tabs', 'teams::member-modal-tab', 15);

        // Teams tab section content (checkboxes + AJAX save)
        $hooks->register('member.modal.sections', 'teams::member-modal-section', 15);

        // JS bridge + member-teams.js entry point
        $hooks->register('member.page.scripts', 'teams::member-page-scripts', 15);

        // ── Extend Management ─────────────────────────────────────────────────

        // Team-grouped list views — replaces the flat default table entirely.
        $hooks->register('management.function.list', 'teams::management-function-list', 10);
        $hooks->register('management.task.list',     'teams::management-task-list', 10);

        // Team dropdown rendered inside the task create/edit form.
        $hooks->register('management.task.modal.teams', 'teams::management-task-modal-teams', 10);

        // ── Extend Events ─────────────────────────────────────────────────────

        $hooks->register('event.table.teams.header', 'teams::event-teams-index-header', 10);
        $hooks->register('event.table.teams.row',    'teams::event-teams-index-row', 10);
        $hooks->register('events.show.teams-panel',  'teams::event-show-teams-panel', 10);
    }

    /**
     * Registers View Composers for all Teams hook-views that require DB/Eloquent data.
     *
     * @return void
     */
    private function registerViewComposers(): void
    {
        // ── Members: Team badges per table row ────────────────────────────────
        // $member is forwarded from the Blade scope by the HookRegistry.
        View::composer('teams::member-table-row', function (ViewContract $view): void {
            if (! Schema::hasTable('teams') || ! Schema::hasTable('team_member')) {
                $view->with('ckMemberTeams', collect());
                return;
            }

            $member = $view->getData()['member'] ?? null;
            if (! $member) {
                $view->with('ckMemberTeams', collect());
                return;
            }

            $teamIds = DB::table('team_member')
                ->where('member_id', $member->id)
                ->pluck('team_id')
                ->toArray();

            $view->with('ckMemberTeams', empty($teamIds)
                ? collect()
                : Team::whereIn('id', $teamIds)
                      ->select('id', 'name', 'color')
                      ->orderBy('name')
                      ->get());
        });

        // ── Members: Modal section – all teams as checkboxes ──────────────────
        View::composer('teams::member-modal-section', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                $view->with('ckAllTeams', collect());
                return;
            }
            $view->with('ckAllTeams', Team::orderBy('name')->get());
        });

        // ── Members: Page scripts – JS bridge with memberId → teamIds map ─────
        // Only queries team_member for the member IDs on the current paginated page.
        View::composer('teams::member-page-scripts', function (ViewContract $view): void {
            if (! Schema::hasTable('teams') || ! Schema::hasTable('team_member')) {
                $view->with('ckMemberTeamMap', []);
                return;
            }

            $members   = $view->getData()['members'] ?? collect();
            $memberIds = $members->pluck('id')->toArray();

            if (empty($memberIds)) {
                $view->with('ckMemberTeamMap', []);
                return;
            }

            $rows = DB::table('team_member')
                ->whereIn('member_id', $memberIds)
                ->get(['member_id', 'team_id']);

            $teamMap = [];
            foreach ($rows as $row) {
                $teamMap[$row->member_id][] = $row->team_id;
            }

            $view->with('ckMemberTeamMap', $teamMap);
        });



        // ── Management: Task modal — team dropdown ────────────────────────────
        View::composer('teams::management-task-modal-teams', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                $view->with('ckAllTeams', collect());
                return;
            }
            $view->with('ckAllTeams', Team::orderBy('name')->get());
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

            // Seed $ckByTeam with ALL teams so empty sections are always visible.
            $ckAllTeams = Team::orderBy('name')->get();
            $ckByTeam   = [];
            foreach ($ckAllTeams as $ckTeam) {
                $ckByTeam[$ckTeam->id] = [
                    'name'      => $ckTeam->name,
                    'color'     => $ckTeam->color ?? 'blue',
                    'functions' => [],
                ];
            }
            foreach ($ckDisplay->filter(fn ($f) => $f->teams->isNotEmpty()) as $ckFn) {
                foreach ($ckFn->teams as $ckTeam) {
                    if (isset($ckByTeam[$ckTeam->id])) {
                        $ckByTeam[$ckTeam->id]['functions'][] = $ckFn;
                    }
                }
            }

            $view->with(compact('ckDisplay', 'ckGeneral', 'ckByTeam'));
        });



        // ── Management: Task list grouped by team ──────────────────────────────
        View::composer('teams::management-task-list', function (ViewContract $view): void {
            if (! Schema::hasTable('teams')) {
                return;
            }

            $tasks = $view->getData()['tasks'] ?? collect();

            // load() existiert nur auf Eloquent\Collection – bei plain Collection überspringen
            if ($tasks instanceof \Illuminate\Database\Eloquent\Collection && $tasks->isNotEmpty()) {
                $tasks->load('teams');
            }

            $ckTeamFilter = request()->integer('team_id', 0) ?: null;
            $ckDisplay    = $ckTeamFilter
                ? $tasks->filter(fn ($t) => $t->teams->contains('id', $ckTeamFilter))
                : $tasks;

            $ckGeneral = $ckDisplay->filter(fn ($t) => $t->teams->isEmpty());

            // Seed $ckByTeam with ALL teams so empty sections are always visible.
            $ckAllTeams = Team::orderBy('name')->get();
            $ckByTeam   = [];
            foreach ($ckAllTeams as $ckTeam) {
                $ckByTeam[$ckTeam->id] = [
                    'name'  => $ckTeam->name,
                    'color' => $ckTeam->color ?? 'blue',
                    'tasks' => [],
                ];
            }
            foreach ($ckDisplay->filter(fn ($t) => $t->teams->isNotEmpty()) as $ckTask) {
                foreach ($ckTask->teams as $ckTeam) {
                    if (isset($ckByTeam[$ckTeam->id])) {
                        $ckByTeam[$ckTeam->id]['tasks'][] = $ckTask;
                    }
                }
            }

            $view->with(compact('ckDisplay', 'ckGeneral', 'ckByTeam'));

            // ── Category grouping (for ?task_group=category toggle) ───────────
            // Seed $ckByCategory with ALL categories so empty sections stay visible.
            // Uses $categories already in view scope (forwarded by HookRegistry).
            $categories        = $view->getData()['categories'] ?? collect();
            $ckCategoryGeneral = $tasks->filter(fn ($t) => is_null($t->category_id));
            $ckByCategory      = [];
            foreach ($categories as $cat) {
                $ckByCategory[$cat->id] = [
                    'name'  => $cat->name,
                    'color' => $cat->color ?? 'blue',
                    'tasks' => [],
                ];
            }
            foreach ($tasks->filter(fn ($t) => ! is_null($t->category_id)) as $ckTask) {
                if (isset($ckByCategory[$ckTask->category_id])) {
                    $ckByCategory[$ckTask->category_id]['tasks'][] = $ckTask;
                }
            }
            $view->with(compact('ckCategoryGeneral', 'ckByCategory'));
        });



        // ── Events: Event detail page teams panel ──────────────────────────────
        View::composer('teams::event-show-teams-panel', function (ViewContract $view): void {
            if (! Schema::hasTable('teams') || ! Schema::hasTable('event_team')) {
                $view->with(['ckShowTeams' => collect(), 'ckAvailableTeams' => collect()]);
                return;
            }

            $event = $view->getData()['event'] ?? null;
            if (! $event) {
                $view->with(['ckShowTeams' => collect(), 'ckAvailableTeams' => collect()]);
                return;
            }

            $ckShowTeamIds = DB::table('event_team')
                ->where('event_id', $event->id)
                ->pluck('team_id')
                ->toArray();

            // Teams currently assigned to this event
            $ckShowTeams = ! empty($ckShowTeamIds)
                ? Team::whereIn('id', $ckShowTeamIds)->orderBy('name')->get()
                : collect();

            // Teams not yet assigned to this event (for the add-select dropdown)
            $ckAvailableTeams = empty($ckShowTeamIds)
                ? Team::orderBy('name')->get()
                : Team::whereNotIn('id', $ckShowTeamIds)->orderBy('name')->get();

            $view->with([
                'ckShowTeams'      => $ckShowTeams,
                'ckAvailableTeams' => $ckAvailableTeams,
            ]);
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