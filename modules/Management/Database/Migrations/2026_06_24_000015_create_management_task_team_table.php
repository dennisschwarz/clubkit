<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_task_team pivot table.
     *
     * team_id is stored without a FK constraint. Management is independent of Teams.
     * Migration 000031 adds the FK conditionally when Teams is installed.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_task_team')) {
            return;
        }

        Schema::create('management_task_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                  ->constrained('management_tasks')
                  ->cascadeOnDelete();

            // No FK on teams – Management is independent of the Teams module.
            // team_id is a reference, not an enforced relation.
            // When a team is deleted the controller cleans up the pivot rows.
            $table->unsignedBigInteger('team_id');

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'team_id']);
            $table->index('team_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('management_task_team');
    }
};
