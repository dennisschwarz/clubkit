<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\YouthClubMode\Http\Controllers\FamilyController;

// Family relation management.
// Note: 'web' and 'auth' middleware are applied by YouthClubModeServiceProvider.
//
// POST   /members/{member}/relations              → create a relation
// DELETE /members/{member}/relations/{relation}   → remove a relation

Route::middleware(['permission:youth-club-mode.manage'])
     ->prefix('members/{member}/relations')
     ->name('youth-club-mode.relations.')
     ->group(function () {

         Route::post('/', [FamilyController::class, 'store'])
              ->name('store');

         Route::delete('/{relation}', [FamilyController::class, 'destroy'])
              ->name('destroy');

     });
