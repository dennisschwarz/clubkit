<?php

use Illuminate\Support\Facades\Route;
use Modules\Teams\Http\Controllers\TeamController;

Route::middleware(['auth'])
    ->prefix('teams')
    ->name('teams.')
    ->group(function () {
        Route::get('/',                               [TeamController::class, 'index'])       ->name('index');
        Route::post('/',                              [TeamController::class, 'store'])       ->name('store');
        Route::get('/{team}',                         [TeamController::class, 'show'])        ->name('show');
        Route::patch('/{team}',                       [TeamController::class, 'update'])      ->name('update');
        Route::delete('/{team}',                      [TeamController::class, 'destroy'])     ->name('destroy');
        Route::post('/{team}/members',                [TeamController::class, 'addMember'])   ->name('addMember');
        Route::delete('/{team}/members/{member}',     [TeamController::class, 'removeMember'])->name('removeMember');
    });
