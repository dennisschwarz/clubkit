<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a real FK (with cascadeOnDelete) on team_id in management_task_team.
 *
 * Guard: only run when both management_task_team and teams tables exist.
 * Management can be operated without the Teams module installed.
 */
return new class extends Migration
{
    /**
     * @return void
     */
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

    /**
     * @return void
     */
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
