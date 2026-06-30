<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Members\Http\Controllers\MemberController;

Route::middleware(['auth'])->prefix('members')->name('members.')->group(function () {

    // Member list
    Route::get('/',                [MemberController::class, 'index'])
        ->name('index')
        ->middleware('permission:members.view');

    // Create new member
    Route::post('/',               [MemberController::class, 'store'])
        ->name('store')
        ->middleware('permission:members.create');

    // Update member
    Route::patch('/{member}',      [MemberController::class, 'update'])
        ->name('update')
        ->middleware('permission:members.edit');

    // Update profile photo
    Route::patch('/{member}/photo', [MemberController::class, 'updatePhoto'])
        ->name('photo')
        ->middleware('permission:members.edit');

    // Delete member
    Route::delete('/{member}',     [MemberController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:members.delete');

});
