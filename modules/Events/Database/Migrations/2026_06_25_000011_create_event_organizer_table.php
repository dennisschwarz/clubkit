<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_organizer')) {
            return;
        }

        Schema::create('event_organizer', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('member_id');
            $table->string('role', 80)->nullable();  // z.B. "Hauptorganisatorin", "Ansprechpartner"
            $table->timestamps();

            $table->foreign('event_id')
                  ->references('id')->on('events')
                  ->cascadeOnDelete();

            $table->foreign('member_id')
                  ->references('id')->on('members')
                  ->cascadeOnDelete();

            $table->unique(['event_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_organizer');
    }
};
