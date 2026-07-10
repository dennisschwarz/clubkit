<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the (event_task_id, member_id) unique index on event_task_members
 * with a three-column index (event_task_id, member_id, time_from).
 *
 * Reason: members may be assigned to the same task in multiple time slots
 * (Einsatzplan). The old two-column index blocked cross-slot assignments
 * and caused SQLSTATE[23000] 1062 Duplicate entry errors.
 *
 * The new index still prevents the exact duplicate:
 * same member + same task + same start time → 409 Conflict.
 *
 * For task-tab assignments (time_from IS NULL): MySQL treats NULL as distinct
 * in unique indexes (NULL != NULL), so application-level checks in
 * EventTaskMemberController remain responsible for preventing duplicates there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_task_members')) {
            return;
        }

        Schema::table('event_task_members', function (Blueprint $table) {
            // Drop the old two-column unique that blocked cross-slot re-assignment.
            $table->dropUnique('event_task_members_event_task_id_member_id_unique');

            // New: uniqueness per task + member + start time.
            $table->unique(
                ['event_task_id', 'member_id', 'time_from'],
                'event_task_members_task_member_time_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_task_members')) {
            return;
        }

        Schema::table('event_task_members', function (Blueprint $table) {
            $table->dropUnique('event_task_members_task_member_time_unique');

            $table->unique(
                ['event_task_id', 'member_id'],
                'event_task_members_event_task_id_member_id_unique'
            );
        });
    }
};
