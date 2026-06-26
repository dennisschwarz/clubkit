<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            // Kein FK auf teams – Management ist unabhängig vom Teams-Modul.
            // team_id ist eine Referenz, keine erzwungene Relation.
            // Beim Löschen eines Teams muss der Controller aufräumen.
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

    public function down(): void
    {
        Schema::dropIfExists('management_task_team');
    }
};
