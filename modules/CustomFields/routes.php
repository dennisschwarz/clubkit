<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CustomFields\Http\Controllers\CustomFieldDefinitionController;
use Modules\CustomFields\Http\Controllers\CustomFieldValueController;

// Hinweis: 'web'-Middleware wird bereits vom CustomFieldsServiceProvider hinzugefügt.
Route::middleware(['auth'])->prefix('custom-fields')->name('custom-fields.')->group(function () {

    // ── Feld-Definitionen (nur Admins) ────────────────────────────────────────
    // Definitionen werden ausschließlich über die Modul-Einstellungsseite verwaltet.
    // Der Index leitet auf admin.module-settings.index weiter.

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

    // ── Feldwerte per Objekt-Typ (Nutzer mit Schreibrecht auf das jeweilige Modul) ──
    // Das `custom-fields.manage`-Recht wird bei Modulinstallation automatisch
    // an die admin-Rolle vergeben. Eigene Rollen können es zusätzlich erhalten.

    Route::get('/values/{objectType}', [CustomFieldValueController::class, 'index'])
        ->name('values.index')
        ->middleware('permission:custom-fields.view');

    Route::post('/values/{objectType}/{entityId}', [CustomFieldValueController::class, 'upsert'])
        ->name('values.upsert')
        ->middleware('permission:custom-fields.manage');

});
