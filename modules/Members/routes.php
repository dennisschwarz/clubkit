<?php

use Illuminate\Support\Facades\Route;
use Modules\Members\Http\Controllers\MemberController;

/*
 * Members Module Routes
 *
 * Middleware: nur 'auth' – Permissions werden über @can in den Views geprüft.
 * Admin-Rolle hat automatisch Zugriff auf alle Tabs (siehe Layout).
 * Fein-granulare Permissions können später über den Module-Tab zugewiesen werden.
 */
Route::middleware(['auth'])
    ->prefix('members')
    ->name('members.')
    ->group(function () {
        Route::get('/',              [MemberController::class, 'index'])  ->name('index');
        Route::get('/create',        [MemberController::class, 'create']) ->name('create');
        Route::post('/',             [MemberController::class, 'store'])  ->name('store');
        Route::get('/{member}/edit', [MemberController::class, 'edit'])   ->name('edit');
        Route::patch('/{member}',    [MemberController::class, 'update']) ->name('update');
        Route::delete('/{member}',   [MemberController::class, 'destroy'])->name('destroy');
    });
