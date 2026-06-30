<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a real FK (with cascadeOnDelete) on team_id in management_function_team.
 *
 * The original migration (000011) deliberately created team_id without a FK
 * so that Management remains independent of Teams. This migration adds the FK
 * conditionally: it only runs when BOTH tables exist (i.e. Teams is installed).
 *
 * Guard: only run when both management_function_team and teams tables exist.
 * Management can be operated without the Teams module installed.
 */
return new class extends Migration
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
