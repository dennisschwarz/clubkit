<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds four Einsatzplan slot-configuration columns to event_tasks.
 *
 * These columns define the staffing-schedule grid for each event-day task:
 *
 *   slot_start_time         → grid start time, H:i:s, e.g. "10:00:00"
 *   slot_end_time           → grid end   time, H:i:s, e.g. "18:00:00"
 *   slot_interval_minutes   → column width in minutes (30 | 60 | 90 | 120)
 *   slot_capacity           → maximum persons per time-slot cell (default 1)
 *
 * A task with NULL slot_start_time / slot_end_time / slot_interval_minutes has
 * not been configured for the grid yet and appears in the "unconfigured" list.
 *
 * Guard: silently skips when event_tasks does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('event_tasks')) {
            return;
        }

        // Idempotency: skip when columns already exist (re-run after failure).
        if (Schema::hasColumn('event_tasks', 'slot_start_time')) {
            return;
        }

        Schema::table('event_tasks', function (Blueprint $table) {
            // Stored as MySQL TIME (H:i:s). Read back and formatted to H:i in PHP.
            $table->time('slot_start_time')->nullable()->after('notes');
            $table->time('slot_end_time')->nullable()->after('slot_start_time');

            // Interval in minutes. Tiny integer: valid values 30, 60, 90, 120.
            $table->unsignedTinyInteger('slot_interval_minutes')->nullable()->after('slot_end_time');

            // Maximum persons per time-slot cell. Default 1 (one person per slot).
            $table->unsignedTinyInteger('slot_capacity')->default(1)->after('slot_interval_minutes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('event_tasks')) {
            return;
        }

        if (! Schema::hasColumn('event_tasks', 'slot_start_time')) {
            return;
        }

        Schema::table('event_tasks', function (Blueprint $table) {
            $table->dropColumn(['slot_start_time', 'slot_end_time', 'slot_interval_minutes', 'slot_capacity']);
        });
    }
};
