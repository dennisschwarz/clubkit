<?php

namespace App\Providers;

use App\Services\ModuleLoader;
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
    }
}
