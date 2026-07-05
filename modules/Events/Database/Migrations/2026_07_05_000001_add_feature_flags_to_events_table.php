<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds per-event feature flags to the events table.
 *
 * These three boolean columns allow administrators to configure which
 * management-related tabs are visible on a per-event basis:
 *
 *   tasks_enabled     → Show the "Aufgaben" tab      (event task management)
 *   functions_enabled → Show the "Funktionen" tab    (Vereinsfunktionen assignment)
 *   slots_enabled     → Show the "Einsatzplan" tab   (time-slot staffing)
 *
 * All three default to TRUE so existing events are unaffected after the migration.
 *
 * When a flag is FALSE, the corresponding tab is:
 *   - Not rendered in show.blade.php
 *   - Not visible in the tab bar (no orphaned empty pane)
 *
 * The flags are set in the create modal (index.blade.php) and the edit modal
 * (show.blade.php editEventModal). They are handled by EventController::store()
 * and EventController::update().
 *
 * Guard: safe to run if the column already exists (idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            if (! Schema::hasColumn('events', 'tasks_enabled')) {
                $table->boolean('tasks_enabled')
                      ->default(true)
                      ->after('notes')
                      ->comment('Show the Aufgaben tab for this event.');
            }

            if (! Schema::hasColumn('events', 'functions_enabled')) {
                $table->boolean('functions_enabled')
                      ->default(true)
                      ->after('tasks_enabled')
                      ->comment('Show the Funktionen tab for this event.');
            }

            if (! Schema::hasColumn('events', 'slots_enabled')) {
                $table->boolean('slots_enabled')
                      ->default(true)
                      ->after('functions_enabled')
                      ->comment('Show the Einsatzplan tab for this event.');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $columns = array_filter(
                ['tasks_enabled', 'functions_enabled', 'slots_enabled'],
                fn (string $col) => Schema::hasColumn('events', $col)
            );

            if (! empty($columns)) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
