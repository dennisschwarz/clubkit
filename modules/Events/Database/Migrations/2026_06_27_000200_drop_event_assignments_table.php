<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the event_assignments table (formerly event_organizer).
 *
 * Background: the "einmalige Zuweisung" concept (free-text member assignment
 * to an event without a task template) has been retired. All member assignments
 * now follow the structured path:
 *
 *   management_tasks → event_task → event_task_member
 *
 * Why the MySQL FK-check workaround?
 * The table was originally created as `event_organizer`. MySQL does NOT rename
 * foreign key constraints when a table is renamed, so the constraints are still
 * named `event_organizer_event_id_foreign` and `event_organizer_member_id_foreign`.
 * Dropping them by their expected new names would fail with error 1091.
 * Temporarily disabling FK checks is therefore the correct approach — but only
 * on MySQL, because `SET FOREIGN_KEY_CHECKS` is MySQL-specific syntax and will
 * cause a syntax error on SQLite (used in the test environment).
 */
return new class extends Migration
{
    /**
     * Drops the event_assignments pivot table.
     *
     * Disables FK checks on MySQL to bypass the renamed-constraint problem.
     * On SQLite (tests) this statement is skipped; SQLite drops tables without
     * requiring explicit FK constraint removal.
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_assignments')) {
            return;
        }

        $isMySQL = DB::getDriverName() === 'mysql';

        if ($isMySQL) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        Schema::dropIfExists('event_assignments');

        if ($isMySQL) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Recreates the event_assignments table so the migration can be rolled back.
     *
     * Note: rolling back after data was already deleted will result in an
     * empty table — the original data is not recoverable via rollback alone.
     */
    public function down(): void
    {
        if (Schema::hasTable('event_assignments')) {
            return;
        }

        Schema::create('event_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete();
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }
};
