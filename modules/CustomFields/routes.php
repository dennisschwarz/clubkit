<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CustomFields\Http\Controllers\CustomFieldDefinitionController;
use Modules\CustomFields\Http\Controllers\CustomFieldValueController;

// Note: 'web' middleware is already applied by CustomFieldsServiceProvider.
Route::middleware(['auth'])->prefix('custom-fields')->name('custom-fields.')->group(function () {

    // ── Field definitions (admins only) ───────────────────────────────────────
    // Definitions are managed exclusively on the module settings page.
    // The index route redirects to admin.module-settings.index.

    Route::get('/',        [CustomFieldDefinitionController::class, 'index'])
        ->name('index')
        ->middleware('permission:custom-fields.manage');

    Route::post('/',       [CustomFieldDefinitionController::class, 'store'])
        ->name('store')
        ->middleware('permission:custom-fields.manage');

    Route::patch('/{id}',  [CustomFieldDefinitionController::class, 'update'])
        ->name('update')
        ->middleware('permission:custom-fields.manage');

    Route::delete('/{id}', [CustomFieldDefinitionController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:custom-fields.manage');

    // ── Field values per object type (users with write access to the module) ──
    // The custom-fields.manage permission is automatically assigned to the admin
    // role on module installation. Custom roles can be granted it additionally.

    Route::get('/values/{objectType}', [CustomFieldValueController::class, 'index'])
        ->name('values.index')
        ->middleware('permission:custom-fields.view');

    Route::post('/values/{objectType}/{entityId}', [CustomFieldValueController::class, 'upsert'])
        ->name('values.upsert')
        ->middleware('permission:custom-fields.manage');

});
