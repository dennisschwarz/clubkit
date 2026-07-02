<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Import\Http\Controllers\ImportController;

// Permission design:
//   import.view    = can use the import wizard (upload, map, preview) — read-only intent
//   import.execute = can actually run the final import and write members to DB
//
// The upload and mapping steps are "non-destructive" (they only create a transient
// ImportSession with a 2h TTL), so import.view is sufficient.
// Only the final execute step writes permanent data and requires import.execute.

Route::middleware(['auth'])->prefix('mitglieder/import')->name('import.')->group(function () {

    // Step 1: upload form
    Route::get('/', [ImportController::class, 'index'])
         ->name('index')
         ->middleware('permission:import.view');

    // Step 1 → 2: upload and parse file (transient ImportSession only — import.view sufficient)
    Route::post('/upload', [ImportController::class, 'upload'])
         ->name('upload')
         ->middleware('permission:import.view');

    // Step 2: column mapping form
    Route::get('/{session}/mapping', [ImportController::class, 'mapping'])
         ->name('mapping')
         ->middleware('permission:import.view');

    // Step 2 → 3: save mapping and compare rows (transient — import.view sufficient)
    Route::post('/{session}/mapping', [ImportController::class, 'saveMapping'])
         ->name('mapping.save')
         ->middleware('permission:import.view');

    // Step 3: preview
    Route::get('/{session}/preview', [ImportController::class, 'preview'])
         ->name('preview')
         ->middleware('permission:import.view');

    // Run the final import (writes permanent member data — requires import.execute)
    Route::post('/{session}/execute', [ImportController::class, 'execute'])
         ->name('execute')
         ->middleware('permission:import.execute');

    // Cancel import (discards ImportSession — import.view sufficient)
    Route::post('/{session}/cancel', [ImportController::class, 'cancel'])
         ->name('cancel')
         ->middleware('permission:import.view');

});
