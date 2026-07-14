<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Members\Models\Member;
use Modules\Teams\Http\Controllers\TeamController;

// Note: 'web' middleware is already applied by TeamsServiceProvider.
Route::middleware(['auth'])->prefix('teams')->name('teams.')->group(function () {

    // Team list
    Route::get('/',         [TeamController::class, 'index'])
        ->name('index')
        ->middleware('permission:teams.view');

    // Team detail
    Route::get('/{team}',   [TeamController::class, 'show'])
        ->name('show')
        ->middleware('permission:teams.view');

    // Create team
    Route::post('/',        [TeamController::class, 'store'])
        ->name('store')
        ->middleware('permission:teams.manage');

    // Update team
    Route::patch('/{team}', [TeamController::class, 'update'])
        ->name('update')
        ->middleware('permission:teams.manage');

    // Delete team
    Route::delete('/{team}',[TeamController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:teams.manage');

    // Sync team roster from Dual Listbox modal (replaces addMembers batch route)
    Route::put('/{team}/members/sync', [TeamController::class, 'syncRoster'])
        ->name('syncRoster')
        ->middleware('permission:teams.members.manage');

    // Sync a member's teams from the member modal Team-Tab (AJAX + form fallback)
    Route::put('/member/{member}/sync', [TeamController::class, 'syncMemberTeams'])
        ->name('syncMemberTeams')
        ->middleware('permission:teams.members.manage');

    // Sorted member rows fragment — used by ckTeamSort() AJAX column sort.
    // Returns rendered <tr> rows (text/html) for the given team, sorted by ?sort=column.
    Route::get('/{team}/members/sort-fragment', [TeamController::class, 'membersFragment'])
        ->name('membersFragment')
        ->middleware('permission:teams.view');

    // Add single member to squad with optional squad number (teams::show page)
    Route::post('/{team}/members',  [TeamController::class, 'addMember'])
        ->name('addMember')
        ->middleware('permission:teams.members.manage');

    // Remove member from squad
    Route::delete('/{team}/members/{member}', [TeamController::class, 'removeMember'])
        ->name('removeMember')
        ->middleware('permission:teams.members.manage');

});