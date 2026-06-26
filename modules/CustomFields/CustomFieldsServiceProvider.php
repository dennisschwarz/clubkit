<?php

declare(strict_types=1);

namespace Modules\CustomFields;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class CustomFieldsServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->loadMigrations();
        $this->registerHooks();
    }

    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'custom-fields');
    }

    private function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    private function registerHooks(): void
    {
        $hooks = $this->app->make('ck.hooks');

        // ── Modul-Einstellungen ──────────────────────────────────────────────
        $hooks->register('admin.module-settings.sections', 'custom-fields::module-settings-section', 20);

        // ── Mitglieder-Modal ─────────────────────────────────────────────────
        $hooks->register('member.modal.tabs',     'custom-fields::member-cf-modal-tab',     90);
        $hooks->register('member.modal.sections', 'custom-fields::member-cf-modal-section', 90);

        // ── Teams-Modal ──────────────────────────────────────────────────────
        $hooks->register('team.modal.tabs',     'custom-fields::team-cf-modal-tab',     90);
        $hooks->register('team.modal.sections', 'custom-fields::team-cf-modal-section', 90);

        // ── Termine-Modal ────────────────────────────────────────────────────
        $hooks->register('event.modal.tabs',     'custom-fields::event-cf-modal-tab',     90);
        $hooks->register('event.modal.sections', 'custom-fields::event-cf-modal-section', 90);

        // ── Management: Funktionen-Modal ─────────────────────────────────────
        $hooks->register('management.function.modal.tabs',     'custom-fields::management-function-cf-modal-tab',     90);
        $hooks->register('management.function.modal.sections', 'custom-fields::management-function-cf-modal-section', 90);

        // ── Management: Aufgaben-Modal ───────────────────────────────────────
        $hooks->register('management.task.modal.tabs',     'custom-fields::management-task-cf-modal-tab',     90);
        $hooks->register('management.task.modal.sections', 'custom-fields::management-task-cf-modal-section', 90);
    }
}
