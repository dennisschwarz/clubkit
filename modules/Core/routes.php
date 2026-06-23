<?php

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Controllers\Admin\ModuleController;
use Modules\Core\Http\Controllers\Admin\SystemController;
use Modules\Core\Http\Controllers\Admin\UserController;
use Modules\Core\Http\Controllers\DashboardController;

// Dashboard
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Admin
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {

        Route::redirect('/', '/admin/system');

        // System
        Route::prefix('system')->name('system.')->group(function () {
            Route::get('/',        [SystemController::class, 'index'])        ->name('index');
            Route::post('migrate', [SystemController::class, 'runMigrations'])->name('migrate');
        });

        // Nutzer
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/',          [UserController::class, 'index'])  ->name('index');
            Route::get('/create',    [UserController::class, 'create']) ->name('create');
            Route::post('/',         [UserController::class, 'store'])  ->name('store');
            Route::get('/{user}',    [UserController::class, 'show'])   ->name('show');
            Route::patch('/{user}',  [UserController::class, 'update']) ->name('update');
            Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
        });

        // Module
        Route::prefix('modules')->name('modules.')->group(function () {
            Route::get('/',                   [ModuleController::class, 'index'])     ->name('index');
            Route::post('/{slug}/install',    [ModuleController::class, 'install'])   ->name('install');
            Route::post('/{slug}/activate',   [ModuleController::class, 'activate'])  ->name('activate');
            Route::post('/{slug}/deactivate', [ModuleController::class, 'deactivate'])->name('deactivate');
            Route::delete('/{slug}',          [ModuleController::class, 'remove'])    ->name('remove');
        });
    });
