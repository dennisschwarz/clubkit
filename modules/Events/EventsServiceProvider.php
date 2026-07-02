<?php

declare(strict_types=1);

namespace Modules\Events;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Bootstraps the Events module.
 *
 * Registers routes, views, and migrations.
 * Depends on: Core, Members.
 * Optionally extends with: Management (tasks/functions), Teams.
 */
class EventsServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void {}

    /** @return void */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
    }

    /** @return void */
    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    /** @return void */
    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'events');
    }
}
