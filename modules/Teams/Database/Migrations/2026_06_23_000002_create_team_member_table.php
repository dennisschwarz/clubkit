<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_member')) return;

        Schema::create('team_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                  ->constrained('teams')
                  ->onDelete('cascade');
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->onDelete('cascade');
            $table->unsignedSmallInteger('squad_number')->nullable();
            $table->timestamps();

            // Ein Mitglied kann einem Team nur einmal angehören
            $table->unique(['team_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member');
    }
};
