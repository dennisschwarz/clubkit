<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventController;
use Modules\Events\Http\Controllers\EventTaskMemberController;
use Modules\Events\Http\Controllers\EventSlotController;

// 'web' middleware is applied by EventsServiceProvider.
Route::middleware(['auth'])->prefix('events')->name('events.')->group(function () {

    // Event list + quick-create modal
    Route::get('/', [EventController::class, 'index'])
        ->name('index')
        ->middleware('permission:events.view');

    Route::post('/', [EventController::class, 'store'])
        ->name('store')
        ->middleware('permission:events.manage');

    // ── Event Detail Page ─────────────────────────────────────────────────────

    Route::get('/{event}', [EventController::class, 'show'])
        ->name('show')
        ->middleware('permission:events.view');

    Route::patch('/{event}', [EventController::class, 'update'])
        ->name('update')
        ->middleware('permission:events.manage');

    Route::delete('/{event}', [EventController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:events.manage');

    // ── Task AJAX endpoints (called from the detail page) ─────────────────────

    Route::patch('/{event}/tasks/{task}/complete', [EventController::class, 'completeTask'])
        ->name('tasks.complete')
        ->middleware('permission:events.manage');

    Route::post('/{event}/tasks', [EventController::class, 'addTask'])
        ->name('tasks.add')
        ->middleware('permission:events.manage');

    Route::delete('/{event}/tasks/{task}', [EventController::class, 'removeTask'])
        ->name('tasks.remove')
        ->middleware('permission:events.manage');

    // ── Member assignments WITHOUT time slot (Aufgaben-Tab inline dropdown) ───
    // POST   body: { task_id, member_id }
    // DELETE {assignment} = EventTaskMember id (time_from must be null)

    Route::post('/{event}/members', [EventTaskMemberController::class, 'store'])
        ->name('members.store')
        ->middleware('permission:events.manage');

    Route::delete('/{event}/members/{assignment}', [EventTaskMemberController::class, 'destroy'])
        ->name('members.destroy')
        ->middleware('permission:events.manage');

    // ── Time-slotted assignments (Einsatzplan-Tab modal) ─────────────────────
    // POST   body: { task_id, member_id, time_from, time_to }
    // DELETE {slot} = EventTaskMember id (time_from must NOT be null)

    Route::post('/{event}/slots', [EventSlotController::class, 'store'])
        ->name('slots.store')
        ->middleware('permission:events.manage');

    Route::delete('/{event}/slots/{slot}', [EventSlotController::class, 'destroy'])
        ->name('slots.destroy')
        ->middleware('permission:events.manage');
});