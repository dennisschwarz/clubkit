<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\YouthClubMode\Http\Controllers\ParentLinkController;

// Einzige Route des Moduls: Eltern-Verknüpfung speichern
// PATCH /members/{member}/parents
Route::patch('/members/{member}/parents', [ParentLinkController::class, 'update'])
     ->name('youth-club-mode.parents.update');
