<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Management\Http\Controllers\EventSlotController;
use Modules\Management\Http\Controllers\EventTaskCategoryController;
use Modules\Management\Http\Controllers\EventTaskController;
use Modules\Management\Http\Controllers\EventTaskMemberController;
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

// ── Event-specific task and assignment routes (Management extends Events) ──────
//
// These routes are registered by the Management module service provider and are
// therefore only active when Management is installed. They live under the
// /events/{event}/... URL space because they operate on event-bound data.
//
// Permission: events.manage (same as other event write operations).
// The Event route model binding resolves via Events module (implicit dependency).

Route::middleware(['auth'])->prefix('events/{event}')->name('events.management.')->group(function () {

    // ── Event task categories ─────────────────────────────────────────────────

    Route::post('/task-categories', [EventTaskCategoryController::class, 'store'])
        ->name('task-categories.store')
        ->middleware('permission:events.manage');

    Route::patch('/task-categories/{categoryId}', [EventTaskCategoryController::class, 'update'])
        ->name('task-categories.update')
        ->middleware('permission:events.manage');

    Route::delete('/task-categories/{categoryId}', [EventTaskCategoryController::class, 'destroy'])
        ->name('task-categories.destroy')
        ->middleware('permission:events.manage');

    // ── Event tasks ───────────────────────────────────────────────────────────

    Route::post('/tasks', [EventTaskController::class, 'store'])
        ->name('tasks.store')
        ->middleware('permission:events.manage');

    Route::patch('/tasks/{taskId}', [EventTaskController::class, 'update'])
        ->name('tasks.update')
        ->middleware('permission:events.manage');

    Route::patch('/tasks/{taskId}/complete', [EventTaskController::class, 'complete'])
        ->name('tasks.complete')
        ->middleware('permission:events.manage');

    Route::patch('/tasks/{taskId}/move', [EventTaskController::class, 'move'])
        ->name('tasks.move')
        ->middleware('permission:events.manage');

    Route::delete('/tasks/{taskId}', [EventTaskController::class, 'destroy'])
        ->name('tasks.destroy')
        ->middleware('permission:events.manage');

    // ── Member assignments (tasks tab — no time window) ───────────────────────

    Route::post('/members', [EventTaskMemberController::class, 'store'])
        ->name('members.store')
        ->middleware('permission:events.manage');

    Route::delete('/members/{assignmentId}', [EventTaskMemberController::class, 'destroy'])
        ->name('members.destroy')
        ->middleware('permission:events.manage');

    // ── Time-slotted assignments (Einsatzplan tab) ────────────────────────────

    Route::post('/slots', [EventSlotController::class, 'store'])
        ->name('slots.store')
        ->middleware('permission:events.manage');

    Route::delete('/slots/{slotId}', [EventSlotController::class, 'destroy'])
        ->name('slots.destroy')
        ->middleware('permission:events.manage');
});