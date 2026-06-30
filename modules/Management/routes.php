<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Management\Http\Controllers\ManagementController;
use Modules\Management\Http\Controllers\TaskCategoryController;

// Note: 'web' middleware is already applied by ManagementServiceProvider.
Route::middleware(['auth'])->prefix('management')->name('management.')->group(function () {

    // Overview (sub-tabs: Functions, Tasks)
    Route::get('/', [ManagementController::class, 'index'])
        ->name('index')
        ->middleware('permission:management.view');

    // ── Functions ─────────────────────────────────────────────────────────────

    Route::post('/functions', [ManagementController::class, 'storeFunction'])
        ->name('functions.store')
        ->middleware('permission:management.functions.manage');

    Route::patch('/functions/{function}', [ManagementController::class, 'updateFunction'])
        ->name('functions.update')
        ->middleware('permission:management.functions.manage');

    Route::delete('/functions/{function}', [ManagementController::class, 'destroyFunction'])
        ->name('functions.destroy')
        ->middleware('permission:management.functions.manage');

    // ── Tasks ─────────────────────────────────────────────────────────────────

    Route::post('/tasks', [ManagementController::class, 'storeTask'])
        ->name('tasks.store')
        ->middleware('permission:management.tasks.manage');

    Route::patch('/tasks/{task}', [ManagementController::class, 'updateTask'])
        ->name('tasks.update')
        ->middleware('permission:management.tasks.manage');

    Route::delete('/tasks/{task}', [ManagementController::class, 'destroyTask'])
        ->name('tasks.destroy')
        ->middleware('permission:management.tasks.manage');

    // ── Task Categories (managed via Module Settings) ─────────────────────────

    Route::post('/task-categories', [TaskCategoryController::class, 'store'])
        ->name('task-categories.store')
        ->middleware('permission:management.tasks.manage');

    Route::patch('/task-categories/{taskCategory}', [TaskCategoryController::class, 'update'])
        ->name('task-categories.update')
        ->middleware('permission:management.tasks.manage');

    Route::delete('/task-categories/{taskCategory}', [TaskCategoryController::class, 'destroy'])
        ->name('task-categories.destroy')
        ->middleware('permission:management.tasks.manage');
});
