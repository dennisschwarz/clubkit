<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ModuleLoader;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Primary application service provider.
 *
 * Responsible for:
 * - Registering the ModuleLoader singleton
 * - Booting all active ClubKit modules
 * - Configuring pagination to use the ClubKit-specific view
 * - Registering the super-admin gate bypass
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Register ModuleLoader as a singleton so all modules share one instance
        $this->app->singleton(ModuleLoader::class);
    }

    /**
     * Bootstrap application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Boot all active modules and register their ServiceProviders
        $this->app->make(ModuleLoader::class)->boot();

        // Use the ClubKit-specific pagination view instead of the Tailwind default.
        // Laravel's default view uses Tailwind CSS classes (w-5, h-5, …) which are
        // not available in this project and would cause SVG icons to render oversized.
        Paginator::defaultView('pagination.ck-pagination');
        Paginator::defaultSimpleView('pagination.ck-pagination');

        // Super-admin gate bypass: super-admin may perform any action without
        // requiring individual permissions.
        //
        // We use roles()->exists() instead of hasRole() (Spatie method) because
        // hasRole() relies on Spatie's internal PermissionRegistrar cache, which
        // can be unreliable between test assertions and Gate::before invocations.
        // The direct relationship query always hits the database fresh.
        Gate::before(function ($user, string $ability) {
            try {
                if (
                    method_exists($user, 'roles')
                    && $user->roles()->where('name', 'super-admin')->exists()
                ) {
                    return true;
                }
            } catch (\Throwable) {
                // Ignore if the database is not yet ready (e.g. during a fresh install)
            }

            return null;
        });
    }
}
