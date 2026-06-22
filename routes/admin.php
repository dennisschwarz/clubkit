<?php

/**
 * Admin Routes
 * Datei: routes/admin.php
 *
 * In routes/web.php einbinden:
 * require __DIR__ . '/admin.php';
 */

use App\Http\Controllers\Admin\SystemController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function (): void {

        // Redirect /admin → /admin/system
        Route::redirect('/', '/admin/system');

        // System & Updates
        Route::get('/system',          [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/migrate', [SystemController::class, 'runMigrations'])->name('system.migrate');

        // Weitere Tabs kommen hier (Teams, Members, Settings, ...)
    });
