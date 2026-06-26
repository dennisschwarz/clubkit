<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\ModuleLoader;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ModuleLoader als Singleton registrieren
        $this->app->singleton(ModuleLoader::class);
    }

    public function boot(): void
    {
        // Alle aktiven Module laden
        $this->app->make(ModuleLoader::class)->boot();

        // Pagination: ClubKit-eigene View statt Tailwind-Default registrieren.
        // Laravels Standard-View nutzt Tailwind-CSS-Klassen (w-5, h-5, …) die
        // in diesem Projekt nicht verfügbar sind → SVG-Icons würden riesig rendern.
        Paginator::defaultView('pagination.ck-pagination');
        Paginator::defaultSimpleView('pagination.ck-pagination');

        // Super-Admin-Bypass: super-admin darf alles, ohne einzelne Permissions zu brauchen.
        //
        // WICHTIG: Wir verwenden roles()->exists() statt hasRole() (Spatie-Methode).
        // hasRole() nutzt Spaties internen PermissionRegistrar-Cache, der zwischen
        // Test-Assertions und dem Gate::before-Aufruf unzuverlässig sein kann.
        // Die direkte Relationship-Abfrage geht immer frisch gegen die DB.
        Gate::before(function ($user, string $ability) {
            try {
                if (
                    method_exists($user, 'roles')
                    && $user->roles()->where('name', 'super-admin')->exists()
                ) {
                    return true;
                }
            } catch (\Throwable) {
                // Ignorieren falls DB noch nicht bereit (z.B. frische Installation)
            }

            return null;
        });
    }
}
