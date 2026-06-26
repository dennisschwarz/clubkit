<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            // Kein FK auf teams – Management ist unabhängig vom Teams-Modul.
            // team_id ist eine Referenz, keine erzwungene Relation.
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

    public function down(): void
    {
        Schema::dropIfExists('management_function_team');
    }
};
