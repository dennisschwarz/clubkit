<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Events\Http\Controllers\EventController;

// Hinweis: 'web'-Middleware wird bereits vom EventsServiceProvider hinzugefügt.
Route::middleware(['auth'])->prefix('events')->name('events.')->group(function () {

    // Termin-Liste
    Route::get('/',           [EventController::class, 'index'])
        ->name('index')
        ->middleware('permission:events.view');

    // Termin anlegen
    Route::post('/',          [EventController::class, 'store'])
        ->name('store')
        ->middleware('permission:events.create');

    // Termin bearbeiten
    Route::patch('/{event}',  [EventController::class, 'update'])
        ->name('update')
        ->middleware('permission:events.edit');

    // Termin löschen
    Route::delete('/{event}', [EventController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:events.delete');

});
