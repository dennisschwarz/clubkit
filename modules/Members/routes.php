<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Members\Http\Controllers\MemberController;

Route::middleware(['auth'])->prefix('members')->name('members.')->group(function () {

    // Mitglieder-Liste
    Route::get('/',                [MemberController::class, 'index'])
        ->name('index')
        ->middleware('permission:members.view');

    // Neues Mitglied anlegen
    Route::post('/',               [MemberController::class, 'store'])
        ->name('store')
        ->middleware('permission:members.create');

    // Mitglied bearbeiten
    Route::patch('/{member}',      [MemberController::class, 'update'])
        ->name('update')
        ->middleware('permission:members.edit');

    // Profilfoto aktualisieren
    Route::patch('/{member}/photo', [MemberController::class, 'updatePhoto'])
        ->name('photo')
        ->middleware('permission:members.edit');

    // Mitglied löschen
    Route::delete('/{member}',     [MemberController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:members.delete');

});
