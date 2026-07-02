<?php

declare(strict_types=1);

namespace Modules\Import;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Import\Importers\DfbNetImporter;
use Modules\Import\Importers\NuLigaImporter;

/**
 * Bootstraps the Import module.
 *
 * Binds the ImporterRegistry as a singleton and registers all built-in importers.
 * External modules can add their own importers by resolving the singleton and calling register().
 * Injects the member.page.actions hook to render the import button without coupling Members to Import.
 */
class ImportServiceProvider extends ServiceProvider
{
    /**
     * Binds the ImporterRegistry singleton and registers built-in importers.
     *
     * External modules can extend the registry via:
     *   app(ImporterRegistry::class)->register(new MyImporter())
     * without modifying ImportController (Open/Closed Principle).
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ImporterRegistry::class, function (): ImporterRegistry {
            $registry = new ImporterRegistry();
            $registry->register(new DfbNetImporter());
            $registry->register(new NuLigaImporter());
            return $registry;
        });
    }

    /** @return void */
    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        $this->registerHooks();
    }

    // ── Private methods (SRP: one responsibility per method) ─────────────────

    /** @return void */
    private function loadRoutes(): void
    {
        Route::middleware('web')->group(__DIR__ . '/routes.php');
    }

    /** @return void */
    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'import');
    }

    /**
     * Registers the import button hook on the members page.
     * Members has no knowledge of Import – fully decoupled via the hook system.
     *
     * @return void
     */
    private function registerHooks(): void
    {
        app('ck.hooks')->register(
            'member.page.actions',
            'import::member-page-action',
            priority: 10,
        );
    }
}
