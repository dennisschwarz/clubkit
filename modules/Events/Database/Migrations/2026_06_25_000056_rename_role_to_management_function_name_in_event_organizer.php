<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benennt event_organizer.role → event_organizer.management_function_name um.
 *
 * "role" war inhaltlich falsch (Verwechslung mit Spatie Permission Roles).
 * Der korrekte Begriff ist "Funktion" im Vereinskontext.
 *
 * Guards: Tabelle + alte Spalte müssen existieren,
 *         neue Spalte darf noch nicht existieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('event_organizer')) {
            return;
        }
        if (!Schema::hasColumn('event_organizer', 'role')) {
            return;
        }
        if (Schema::hasColumn('event_organizer', 'management_function_name')) {
            return;
        }

        Schema::table('event_organizer', function (Blueprint $table) {
            $table->renameColumn('role', 'management_function_name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_organizer')) {
            return;
        }
        if (!Schema::hasColumn('event_organizer', 'management_function_name')) {
            return;
        }

        Schema::table('event_organizer', function (Blueprint $table) {
            $table->renameColumn('management_function_name', 'role');
        });
    }
};
