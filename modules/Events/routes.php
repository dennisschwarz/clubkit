<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventController;

// 'web' middleware is applied by EventsServiceProvider.
Route::middleware(['auth', 'web'])->prefix('events')->name('events.')->group(function () {

    // ── Event list + quick-create modal ──────────────────────────────────────

    Route::get('/', [EventController::class, 'index'])
        ->name('index')
        ->middleware('permission:events.view');

    Route::post('/', [EventController::class, 'store'])
        ->name('store')
        ->middleware('permission:events.manage');

    // ── Event detail page ─────────────────────────────────────────────────────

    Route::get('/{event}', [EventController::class, 'show'])
        ->name('show')
        ->middleware('permission:events.view');

    Route::patch('/{event}', [EventController::class, 'update'])
        ->name('update')
        ->middleware('permission:events.manage');

    Route::delete('/{event}', [EventController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:events.manage');

    // ── Management functions per event ────────────────────────────────────────
    // POST   body: { function_id: int }    → assign function (member_id defaults to null)
    // PATCH  body: { member_id: int|null } → assign or remove a member
    // DELETE                               → remove function from event

    Route::post('/{event}/functions', [EventController::class, 'addFunction'])
        ->name('functions.add')
        ->middleware('permission:events.manage');

    Route::patch('/{event}/functions/{functionId}', [EventController::class, 'assignFunction'])
        ->name('functions.assign')
        ->middleware('permission:events.manage');

    Route::delete('/{event}/functions/{functionId}', [EventController::class, 'removeFunction'])
        ->name('functions.remove')
        ->middleware('permission:events.manage');

    // ── Team assignments per event ────────────────────────────────────────────
    // POST   body: { team_id: int } → assign team to event
    // DELETE /{teamId}              → remove team from event

    Route::post('/{event}/teams', [EventController::class, 'addTeam'])
        ->name('teams.add')
        ->middleware('permission:events.manage');

    Route::delete('/{event}/teams/{teamId}', [EventController::class, 'removeTeam'])
        ->name('teams.remove')
        ->middleware('permission:events.manage');
});
