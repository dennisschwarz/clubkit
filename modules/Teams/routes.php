<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Teams\Http\Controllers\TeamController;

// Note: 'web' middleware is already applied by TeamsServiceProvider.
Route::middleware(['auth'])->prefix('teams')->name('teams.')->group(function () {

    // Team list
    Route::get('/',                [TeamController::class, 'index'])
        ->name('index')
        ->middleware('permission:teams.view');

    // Team detail
    Route::get('/{team}',          [TeamController::class, 'show'])
        ->name('show')
        ->middleware('permission:teams.view');

    // Create team
    Route::post('/',               [TeamController::class, 'store'])
        ->name('store')
        ->middleware('permission:teams.manage');

    // Update team
    Route::patch('/{team}',        [TeamController::class, 'update'])
        ->name('update')
        ->middleware('permission:teams.manage');

    // Delete team
    Route::delete('/{team}',       [TeamController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:teams.manage');

    // Add member to squad
    Route::post('/{team}/members', [TeamController::class, 'addMember'])
        ->name('addMember')
        ->middleware('permission:teams.members.manage');

    // Remove member from squad
    Route::delete('/{team}/members/{member}', [TeamController::class, 'removeMember'])
        ->name('removeMember')
        ->middleware('permission:teams.members.manage');

});
