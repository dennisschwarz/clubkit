<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\AppearanceController;
use Modules\Core\Http\Controllers\Admin\ModuleController;
use Modules\Core\Http\Controllers\Admin\SystemController;
use Modules\Core\Http\Controllers\Admin\UserController;
use Modules\Core\Http\Controllers\DashboardController;

// ── Dashboard ──────────────────────────────────────────────────────────────

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');
    });

// ── Admin-Bereich (nur Admins) ─────────────────────────────────────────────

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

        // Redirect /admin → /admin/system
        Route::redirect('/', '/admin/system');

        // ── System-Überblick ──────────────────────────────────────────────
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/',         [SystemController::class, 'index'])        ->name('index');
            Route::post('/migrate', [SystemController::class, 'runMigrations'])->name('migrate');
        });

        // ── Nutzer-Verwaltung ─────────────────────────────────────────────
        // store   POST   /admin/users          → neuen Nutzer anlegen
        // update  PATCH  /admin/users/{user}   → Login-Infos oder Rechte ändern
        // destroy DELETE /admin/users/{user}   → Nutzer löschen
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',          [UserController::class, 'index'])  ->name('index');
            Route::post('/',         [UserController::class, 'store'])  ->name('store');   // ← NEU
            Route::get('/{user}',    [UserController::class, 'show'])   ->name('show');
            Route::patch('/{user}',  [UserController::class, 'update']) ->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        // ── Modul-Verwaltung ──────────────────────────────────────────────
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/',                    [ModuleController::class, 'index'])     ->name('index');
            Route::post('/{slug}/install',     [ModuleController::class, 'install'])   ->name('install');
            Route::patch('/{slug}/activate',   [ModuleController::class, 'activate'])  ->name('activate');
            Route::patch('/{slug}/deactivate', [ModuleController::class, 'deactivate'])->name('deactivate');
            Route::delete('/{slug}',           [ModuleController::class, 'remove'])    ->name('remove');
        });

        // ── Erscheinungsbild ──────────────────────────────────────────────
        Route::prefix('appearance')->name('appearance.')->group(function () {
            Route::get('/',        [AppearanceController::class, 'index'])     ->name('index');
            Route::patch('/',      [AppearanceController::class, 'update'])    ->name('update');
            Route::delete('/logo', [AppearanceController::class, 'deleteLogo'])->name('logo.delete');
        });

    });
