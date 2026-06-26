<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt einen echten FK (mit CascadeOnDelete) auf team_id hinzu.
 *
 * Die ursprüngliche Migration hat team_id bewusst ohne FK angelegt
 * ("Management unabhängig von Teams"). Das führt aber zu Daten-Waisen wenn
 * Teams gelöscht werden. Diese Migration korrigiert das.
 *
 * Guard: Nur ausführen wenn BEIDE Tabellen existieren – Management kann
 * ohne installiertes Teams-Modul betrieben werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_function_team') || ! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('management_function_team', function (Blueprint $table) {
            $table->foreign('team_id')
                  ->references('id')->on('teams')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_function_team')) {
            return;
        }

        Schema::table('management_function_team', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
    }
};
