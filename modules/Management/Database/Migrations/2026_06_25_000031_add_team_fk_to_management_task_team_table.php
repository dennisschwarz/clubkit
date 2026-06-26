<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt einen echten FK (mit CascadeOnDelete) auf team_id in management_task_team hinzu.
 *
 * Guard: Nur ausführen wenn BEIDE Tabellen existieren – Management kann
 * ohne installiertes Teams-Modul betrieben werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_task_team') || ! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('management_task_team', function (Blueprint $table) {
            $table->foreign('team_id')
                  ->references('id')->on('teams')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_task_team')) {
            return;
        }

        Schema::table('management_task_team', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
    }
};
