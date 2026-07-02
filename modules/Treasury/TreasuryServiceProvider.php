<?php

declare(strict_types=1);

namespace Modules\Treasury;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Treasury\Services\TreasuryVisibilityService;

/**
 * Bootstraps the Treasury module.
 *
 * Registers routes, views, database migrations, and the TreasuryVisibilityService
 * singleton. Also hooks a category-management section into the Module Settings page.
 */
class TreasuryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the visibility service as a singleton so repeated calls
        // within a single request do not re-instantiate the object.
        $this->app->singleton(TreasuryVisibilityService::class);
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->registerHooks();
    }

    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'treasury');
    }

    /**
     * Registers a section into the Module Settings admin page at priority 40.
     *
     * Priority 40 places the Treasury section after Management (30).
     */
    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');
        $hooks->register('admin.module-settings.sections', 'treasury::module-settings-section', 40);
    }
}
