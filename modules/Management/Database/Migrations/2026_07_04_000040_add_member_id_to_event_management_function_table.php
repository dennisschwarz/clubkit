<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the nullable member_id column to event_management_function.
 *
 * Why a separate migration instead of modifying the original create migration?
 *   The create migration (000035) has already been executed in production.
 *   Modifying the original would only affect fresh installs / test environments.
 *   This migration backfills the column for existing installations.
 *
 * Semantics:
 *   NULL = function assigned to this event without a designated member yet.
 *   SET  = member assigned to fulfil this function for this specific event.
 *
 * No DB-level FK: the Events module does not declare a dependency on Members
 * at the schema level. Referential integrity is enforced in EventController.
 *
 * Guard: safe to run even if the column was already added manually.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('event_management_function')) {
            return;
        }

        if (Schema::hasColumn('event_management_function', 'member_id')) {
            return;
        }

        Schema::table('event_management_function', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->after('management_function_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('event_management_function')) {
            return;
        }

        if (! Schema::hasColumn('event_management_function', 'member_id')) {
            return;
        }

        Schema::table('event_management_function', function (Blueprint $table) {
            $table->dropColumn('member_id');
        });
    }
};
