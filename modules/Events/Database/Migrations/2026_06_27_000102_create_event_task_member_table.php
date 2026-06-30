<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_task_member table.
 *
 * Records which member is assigned to a specific task at a specific event,
 * optionally within a time window (e.g. 10:00–12:00 for a shift).
 *
 * A member may only appear once per event-task combination (unique constraint).
 *
 * Why not extend event_task with a single assigned_member_id?
 * Because multiple members can share the same task at the same event
 * (e.g. three people do Getränkeverkauf at different time slots).
 *
 * Cross-module FK note (REGEL 13):
 *   task_id references management_tasks, but Management is an optional module
 *   (not in Events' requires[]). A DB-level FK would cause this migration to
 *   fail when Events is installed without Management. The relation is enforced
 *   at the application layer via EventTaskMember::task().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_task_member')) {
            return;
        }

        Schema::create('event_task_member', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->cascadeOnDelete();

            // No DB-level FK — Management module is optional (REGEL 13).
            $table->unsignedBigInteger('task_id');

            $table->unsignedBigInteger('member_id');
            $table->foreign('member_id')
                  ->references('id')
                  ->on('members')
                  ->cascadeOnDelete();

            $table->dateTime('time_from')->nullable();
            $table->dateTime('time_to')->nullable();
            $table->timestamps();

            // One member per event-task combination.
            $table->unique(['event_id', 'task_id', 'member_id']);

            $table->index('task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task_member');
    }
};
