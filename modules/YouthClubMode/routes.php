<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\YouthClubMode\Http\Controllers\FamilyController;

// Familiäre Verbindungen verwalten
// Hinweis: 'web'- und 'auth'-Middleware werden vom YouthClubModeServiceProvider gesetzt.
//
// POST   /members/{member}/relations          → Verbindung anlegen
// DELETE /members/{member}/relations/{relation} → Verbindung entfernen

Route::middleware(['permission:youth-club-mode.manage'])
     ->prefix('members/{member}/relations')
     ->name('youth-club-mode.relations.')
     ->group(function () {

         Route::post('/', [FamilyController::class, 'store'])
              ->name('store');

         Route::delete('/{relation}', [FamilyController::class, 'destroy'])
              ->name('destroy');

     });
