<?php

declare(strict_types=1);

namespace Modules\Management;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Management\View\Composers\AssignmentsIndexRowComposer;
use Modules\Management\View\Composers\EventSlotsPanelComposer;
use Modules\Management\View\Composers\EventFunctionsPanelComposer;
use Modules\Management\View\Composers\EventHeroFunctionsComposer;
use Modules\Management\View\Composers\EventOverviewPanelComposer;
use Modules\Management\View\Composers\EventShowPageScriptsComposer;
use Modules\Management\View\Composers\EventTasksPanelComposer;

/**
 * Bootstraps the Management module.
 *
 * Registers routes, views, migrations and all cross-module hook views.
 *
 * ── Hook architecture ──────────────────────────────────────────────────────
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
 * ── View Composers ─────────────────────────────────────────────────────────
 *
 * Each view that needs DB data has a dedicated Composer class under
 * Modules\Management\View\Composers\. The ServiceProvider only registers them.
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
        $hooks->register('admin.module-settings.tabs',     'management::module-settings-tab',     30);
        $hooks->register('admin.module-settings.sections', 'management::module-settings-section', 30);

        // ── Extend Events ──────────────────────────────────────────────────

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
     * Registers dedicated View Composer classes for each Management hook view.
     *
     * The module-settings-section view receives a flat list of all global task
     * categories via an inline closure composer (no dedicated class needed).
     *
     * @return void
     */
    private function registerViewComposers(): void
    {
        View::composer('management::event-assignments-index-row', AssignmentsIndexRowComposer::class);
        View::composer('management::event-show-page-scripts',     EventShowPageScriptsComposer::class);
        View::composer('management::event-hero-functions',        EventHeroFunctionsComposer::class);
        View::composer('management::event-overview-panel',        EventOverviewPanelComposer::class);
        View::composer('management::event-tasks-panel',           EventTasksPanelComposer::class);
        View::composer('management::event-slots-panel',           EventSlotsPanelComposer::class);
        View::composer('management::event-functions-panel',       EventFunctionsPanelComposer::class);

        // Supplies global task categories to the module settings admin section.
        View::composer('management::module-settings-section', function (\Illuminate\View\View $view): void {
            $view->with([
                'mgmtTaskCategories' => \Modules\Management\Models\ManagementTaskCategory::orderBy('name')->get(),
            ]);
        });
    }
}