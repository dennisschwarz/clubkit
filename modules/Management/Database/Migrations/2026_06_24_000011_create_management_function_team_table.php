<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_function_team pivot table.
     *
     * team_id is stored without a FK constraint. Management is independent of the Teams module.
     * team_id is a reference, not an enforced relation. Migration 000030 adds the FK
     * conditionally when Teams is installed.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_function_team')) {
            return;
        }

        Schema::create('management_function_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                  ->constrained('management_functions')
                  ->cascadeOnDelete();

            // No FK on teams – Management is independent of the Teams module.
            // team_id is a reference, not an enforced relation.
            $table->unsignedBigInteger('team_id');

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'team_id']);
            $table->index('team_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('management_function_team');
    }
};
