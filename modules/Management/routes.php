<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Management\Http\Controllers\ManagementController;

// Hinweis: 'web'-Middleware wird bereits vom ManagementServiceProvider hinzugefügt.
Route::middleware(['auth'])->prefix('management')->name('management.')->group(function () {

    // Übersicht (Sub-Tabs: Funktionen, Aufgaben)
    Route::get('/', [ManagementController::class, 'index'])
        ->name('index')
        ->middleware('permission:management.view');

    // ── Funktionen ────────────────────────────────────────────────────────────

    Route::post('/functions', [ManagementController::class, 'storeFunction'])
        ->name('functions.store')
        ->middleware('permission:management.functions.manage');

    Route::patch('/functions/{function}', [ManagementController::class, 'updateFunction'])
        ->name('functions.update')
        ->middleware('permission:management.functions.manage');

    Route::delete('/functions/{function}', [ManagementController::class, 'destroyFunction'])
        ->name('functions.destroy')
        ->middleware('permission:management.functions.manage');

    // ── Aufgaben ──────────────────────────────────────────────────────────────

    Route::post('/tasks', [ManagementController::class, 'storeTask'])
        ->name('tasks.store')
        ->middleware('permission:management.tasks.manage');

    Route::patch('/tasks/{task}', [ManagementController::class, 'updateTask'])
        ->name('tasks.update')
        ->middleware('permission:management.tasks.manage');

    Route::delete('/tasks/{task}', [ManagementController::class, 'destroyTask'])
        ->name('tasks.destroy')
        ->middleware('permission:management.tasks.manage');

});
