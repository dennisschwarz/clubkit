<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Treasury\Http\Controllers\TreasuryController;
use Modules\Treasury\Http\Controllers\TreasuryContributionController;

// Note: 'web' middleware is applied in TreasuryServiceProvider.
Route::middleware(['auth'])->prefix('treasury')->name('treasury.')->group(function () {

    // ── Overview ──────────────────────────────────────────────────────────────

    Route::get('/', [TreasuryController::class, 'index'])
        ->name('index')
        ->middleware('permission:treasury.view');

    // ── Transactions ──────────────────────────────────────────────────────────

    Route::post('/transactions', [TreasuryController::class, 'storeTransaction'])
        ->name('transactions.store')
        ->middleware('permission:treasury.transactions.manage');

    Route::patch('/transactions/{transaction}', [TreasuryController::class, 'updateTransaction'])
        ->name('transactions.update')
        ->middleware('permission:treasury.transactions.manage');

    Route::delete('/transactions/{transaction}', [TreasuryController::class, 'destroyTransaction'])
        ->name('transactions.destroy')
        ->middleware('permission:treasury.transactions.manage');

    // ── Accounts ──────────────────────────────────────────────────────────────

    Route::post('/accounts', [TreasuryController::class, 'storeAccount'])
        ->name('accounts.store')
        ->middleware('permission:treasury.accounts.manage');

    Route::patch('/accounts/{account}', [TreasuryController::class, 'updateAccount'])
        ->name('accounts.update')
        ->middleware('permission:treasury.accounts.manage');

    Route::delete('/accounts/{account}', [TreasuryController::class, 'destroyAccount'])
        ->name('accounts.destroy')
        ->middleware('permission:treasury.accounts.manage');

    // ── Categories (managed via Module Settings) ──────────────────────────────

    Route::post('/categories', [TreasuryController::class, 'storeCategory'])
        ->name('categories.store')
        ->middleware('permission:treasury.categories.manage');

    Route::patch('/categories/{category}', [TreasuryController::class, 'updateCategory'])
        ->name('categories.update')
        ->middleware('permission:treasury.categories.manage');

    Route::delete('/categories/{category}', [TreasuryController::class, 'destroyCategory'])
        ->name('categories.destroy')
        ->middleware('permission:treasury.categories.manage');

    // ── Contribution tasks ────────────────────────────────────────────────────

    Route::post('/contributions', [TreasuryContributionController::class, 'store'])
        ->name('contributions.store')
        ->middleware('permission:treasury.contributions.manage');

    Route::delete('/contributions/{taskMeta}', [TreasuryContributionController::class, 'destroy'])
        ->name('contributions.destroy')
        ->middleware('permission:treasury.contributions.manage');

    Route::post('/contributions/{taskMeta}/payments', [TreasuryContributionController::class, 'addMember'])
        ->name('contributions.payments.store')
        ->middleware('permission:treasury.contributions.manage');

    Route::patch('/contributions/{taskMeta}/payments/{payment}/pay', [TreasuryContributionController::class, 'markPaid'])
        ->name('contributions.payments.pay')
        ->middleware('permission:treasury.contributions.manage');

    Route::patch('/contributions/{taskMeta}/payments/{payment}/unpay', [TreasuryContributionController::class, 'markUnpaid'])
        ->name('contributions.payments.unpay')
        ->middleware('permission:treasury.contributions.manage');

    Route::delete('/contributions/{taskMeta}/payments/{payment}', [TreasuryContributionController::class, 'removeMember'])
        ->name('contributions.payments.destroy')
        ->middleware('permission:treasury.contributions.manage');
});
