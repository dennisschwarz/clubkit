<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the event_task pivot with per-assignment metadata.
 *
 * New columns:
 *   notes       → event-specific notes for this task at this event
 *   completed   → whether the task was completed at this event
 *   deadline_at → when the task must be done by (event-specific)
 *
 * These live on the pivot (not on management_tasks) because the values
 * are specific to the combination of event + task, not to the task itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('event_task', 'notes')) {
            return;
        }

        Schema::table('event_task', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('task_id');
            $table->boolean('completed')->default(false)->after('notes');
            $table->dateTime('deadline_at')->nullable()->after('completed');
        });
    }

    public function down(): void
    {
        Schema::table('event_task', function (Blueprint $table) {
            $table->dropColumn(['notes', 'completed', 'deadline_at']);
        });
    }
};
