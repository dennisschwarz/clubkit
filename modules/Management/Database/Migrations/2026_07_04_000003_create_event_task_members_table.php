<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_task_members table.
 *
 * Replaces the former event_task_member pivot (Events module).
 *
 * Records which member is assigned to a specific event task, optionally
 * within a time window for the Einsatzplan (staffing schedule) tab.
 *
 * Key difference from the old event_task_member table:
 *   - event_task_id is a direct FK to event_tasks.id
 *   - Removes the need for the composite (event_id, task_id) key in every query
 *   - Simpler relations and cleaner controller code
 *
 * time_from / time_to semantics:
 *   NULL / NULL  → assigned via the tasks tab (no shift window)
 *   SET  / SET   → assigned via the Einsatzplan tab with a specific time window
 *
 * member_id has no DB-level FK (REGEL 13):
 *   Management's requires[] does not list Members. Referential integrity is
 *   enforced at the application layer (EventTaskMemberController validation).
 *   In practice Members is always installed (Events requires Members).
 *
 * Guard: event_tasks table must exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_tasks')) {
            return;
        }

        if (Schema::hasTable('event_task_members')) {
            return;
        }

        Schema::create('event_task_members', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_task_id')
                  ->constrained('event_tasks')
                  ->cascadeOnDelete();

            // No DB-level FK to members (REGEL 13 — Management does not require Members).
            $table->unsignedBigInteger('member_id');

            $table->dateTime('time_from')->nullable();
            $table->dateTime('time_to')->nullable();

            $table->timestamps();

            // One member per event task (no duplicate assignments).
            $table->unique(['event_task_id', 'member_id']);

            // Index for member-centric queries (e.g. "all tasks for this member").
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task_members');
    }
};
