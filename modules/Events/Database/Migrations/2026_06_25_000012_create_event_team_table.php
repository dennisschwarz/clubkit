<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_team')) {
            return;
        }

        Schema::create('event_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');  // Kein FK – Teams-Modul ist optional
            $table->timestamps();

            $table->foreign('event_id')
                  ->references('id')->on('events')
                  ->cascadeOnDelete();

            $table->unique(['event_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team');
    }
};
