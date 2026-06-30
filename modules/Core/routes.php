<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\ActivityLogController;
use Modules\Core\Http\Controllers\Admin\AppearanceController;
use Modules\Core\Http\Controllers\Admin\ModuleController;
use Modules\Core\Http\Controllers\Admin\ModuleSettingsController;
use Modules\Core\Http\Controllers\Admin\RolesController;
use Modules\Core\Http\Controllers\Admin\SystemController;
use Modules\Core\Http\Controllers\Admin\UserController;
use Modules\Core\Http\Controllers\DashboardController;

// ── Dashboard ─────────────────────────────────────────────────────────────────

Route::middleware(['auth', 'verified'])
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');
    });

// ── Admin area (admins and super-admins only) ─────────────────────────────────

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin|super-admin'])
    ->group(function () {

        // Redirect /admin → /admin/system
        Route::redirect('/', '/admin/system');

        // ── System overview ───────────────────────────────────────────────
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/',         [SystemController::class, 'index'])        ->name('index');
            Route::post('/migrate', [SystemController::class, 'runMigrations'])->name('migrate');
        });

        // ── User management ───────────────────────────────────────────────
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',          [UserController::class, 'index'])  ->name('index');
            Route::post('/',         [UserController::class, 'store'])  ->name('store');
            Route::get('/{user}',    [UserController::class, 'show'])   ->name('show');
            Route::patch('/{user}',  [UserController::class, 'update']) ->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        // ── Roles and permissions ─────────────────────────────────────────
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/',         [RolesController::class, 'index'])  ->name('index');
            Route::post('/',        [RolesController::class, 'store'])  ->name('store');
            Route::patch('/{role}', [RolesController::class, 'update']) ->name('update');
            Route::delete('/{role}',[RolesController::class, 'destroy'])->name('destroy');
        });

        // ── Module management ─────────────────────────────────────────────
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/',                    [ModuleController::class, 'index'])     ->name('index');
            Route::post('/{slug}/install',     [ModuleController::class, 'install'])   ->name('install');
            Route::patch('/{slug}/activate',   [ModuleController::class, 'activate'])  ->name('activate');
            Route::patch('/{slug}/deactivate', [ModuleController::class, 'deactivate'])->name('deactivate');
            Route::delete('/{slug}',           [ModuleController::class, 'remove'])    ->name('remove');
        });

        // ── Module settings (hook-based hub) ──────────────────────────────
        Route::prefix('module-settings')->name('module-settings.')->group(function () {
            Route::get('/', [ModuleSettingsController::class, 'index'])->name('index');
        });

        // ── Appearance ────────────────────────────────────────────────────
        Route::prefix('appearance')->name('appearance.')->group(function () {
            Route::get('/',        [AppearanceController::class, 'index'])     ->name('index');
            Route::patch('/',      [AppearanceController::class, 'update'])    ->name('update');
            Route::delete('/logo', [AppearanceController::class, 'deleteLogo'])->name('logo.delete');
        });

        // ── Activity log ──────────────────────────────────────────────────
        Route::prefix('activity-log')->name('activity-log.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
        });

    });
