<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds sort_order to event_task_members for drag-and-drop member ordering
 * within a task's assignment list.
 *
 * Guard: table must exist + column must not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_task_members')) {
            return;
        }

        if (Schema::hasColumn('event_task_members', 'sort_order')) {
            return;
        }

        Schema::table('event_task_members', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('time_to');
            $table->index(['event_task_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_task_members')) {
            return;
        }

        Schema::table('event_task_members', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
