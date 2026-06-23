<?php

namespace Modules\Core;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadViews();
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(
            __DIR__ . '/Resources/Views/components',
            'ck'
        );
    }

    private function loadRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/routes.php');
    }

    private function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/Resources/Views', 'core');
    }
}
