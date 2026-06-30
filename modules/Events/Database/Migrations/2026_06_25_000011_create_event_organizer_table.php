<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_organizer pivot table (event ↔ member with an optional role label).
 *
 * This table was later renamed to event_assignments by migration 000058.
 * It is dropped in migration 000200 after the event_task_member redesign.
 *
 * cascadeOnDelete on both FKs: an organizer assignment without an event or member is meaningless.
 */
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
            $table->string('role', 80)->nullable();
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
