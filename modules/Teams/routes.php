<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Teams\Http\Controllers\TeamController;

Route::middleware(['auth'])->prefix('teams')->name('teams.')->group(function () {

    // Team-Liste
    Route::get('/',                [TeamController::class, 'index'])
        ->name('index')
        ->middleware('permission:teams.view');

    // Team-Detail
    Route::get('/{team}',          [TeamController::class, 'show'])
        ->name('show')
        ->middleware('permission:teams.view');

    // Team anlegen
    Route::post('/',               [TeamController::class, 'store'])
        ->name('store')
        ->middleware('permission:teams.manage');

    // Team bearbeiten
    Route::patch('/{team}',        [TeamController::class, 'update'])
        ->name('update')
        ->middleware('permission:teams.manage');

    // Team löschen
    Route::delete('/{team}',       [TeamController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:teams.manage');

    // Mitglied in Kader aufnehmen
    Route::post('/{team}/members', [TeamController::class, 'addMember'])
        ->name('addMember')
        ->middleware('permission:teams.members.manage');

    // Mitglied aus Kader entfernen
    Route::delete('/{team}/members/{member}', [TeamController::class, 'removeMember'])
        ->name('removeMember')
        ->middleware('permission:teams.members.manage');

});
