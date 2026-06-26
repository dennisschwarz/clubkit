<?php

declare(strict_types=1);

namespace Modules\Import;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ImportServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerHooks();
    }

    // ── Private Methoden (SRP: eine Aufgabe pro Methode) ──────────────────────

    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'import');
    }

    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    private function registerHooks(): void
    {
        // Import-Button auf der Mitglieder-Seite einblenden.
        // Wird nur aufgerufen wenn dieses Modul aktiv ist.
        // Members weiß nichts von Import – vollständige Entkopplung via Hook.
        app('ck.hooks')->register(
            'member.page.actions',
            'import::member-page-action',
            priority: 10,
        );
    }
}
