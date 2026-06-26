<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Import\Http\Controllers\ImportController;

Route::middleware(['auth'])->prefix('mitglieder/import')->name('import.')->group(function () {

    // Stufe 1: Upload-Formular
    Route::get('/', [ImportController::class, 'index'])
         ->name('index')
         ->middleware('permission:import.view');

    // Stufe 1 → 2: Datei hochladen + parsen
    Route::post('/upload', [ImportController::class, 'upload'])
         ->name('upload')
         ->middleware('permission:import.execute');

    // Stufe 2: Mapping-Formular
    Route::get('/{session}/mapping', [ImportController::class, 'mapping'])
         ->name('mapping')
         ->middleware('permission:import.execute');

    // Stufe 2 → 3: Mapping speichern + Datensätze vergleichen
    Route::post('/{session}/mapping', [ImportController::class, 'saveMapping'])
         ->name('mapping.save')
         ->middleware('permission:import.execute');

    // Stufe 3: Vorschau
    Route::get('/{session}/preview', [ImportController::class, 'preview'])
         ->name('preview')
         ->middleware('permission:import.execute');

    // Finaler Import ausführen
    Route::post('/{session}/execute', [ImportController::class, 'execute'])
         ->name('execute')
         ->middleware('permission:import.execute');

    // Abbrechen
    Route::post('/{session}/cancel', [ImportController::class, 'cancel'])
         ->name('cancel')
         ->middleware('permission:import.view');

});
