<?php

declare(strict_types=1);

namespace Modules\Management;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ManagementServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        // Hinweis: keine Team-Hooks mehr – team_type entfernt.
        // Wenn Rollen-im-Team-UI gebaut wird, hier erneut einhängen.
    }

    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'management');
    }

    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }
}
