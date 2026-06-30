<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the foreign-key column field_id to definition_id in custom_field_values.
 *
 * The column references custom_field_definitions.id. Naming it definition_id
 * follows Laravel's FK naming convention and accurately describes the reference.
 *
 * MySQL  – requires dropping the FK and unique constraint before renaming,
 *          then re-adding them under the new column name.
 * SQLite – ALTER TABLE RENAME COLUMN preserves and updates all indexes
 *          automatically; explicit constraint management is not supported
 *          and not needed.
 *
 * The migration is idempotent: it exits early when the target state already exists.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('custom_field_values')) return;
        if (! Schema::hasColumn('custom_field_values', 'field_id')) return;
        if (Schema::hasColumn('custom_field_values', 'definition_id')) return;

        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                $table->dropForeign(['field_id']);
                $table->dropUnique(['field_id', 'entity_id']);
            });
        }

        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->renameColumn('field_id', 'definition_id');
        });

        if ($isMysql) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                $table->foreign('definition_id')
                      ->references('id')->on('custom_field_definitions')
                      ->cascadeOnDelete();
                $table->unique(['definition_id', 'entity_id']);
            });
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('custom_field_values')) return;
        if (! Schema::hasColumn('custom_field_values', 'definition_id')) return;
        if (Schema::hasColumn('custom_field_values', 'field_id')) return;

        $isMysql = DB::getDriverName() === 'mysql';

        if ($isMysql) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                $table->dropForeign(['definition_id']);
                $table->dropUnique(['definition_id', 'entity_id']);
            });
        }

        Schema::table('custom_field_values', function (Blueprint $table) {
            $table->renameColumn('definition_id', 'field_id');
        });

        if ($isMysql) {
            Schema::table('custom_field_values', function (Blueprint $table) {
                $table->foreign('field_id')
                      ->references('id')->on('custom_field_definitions')
                      ->cascadeOnDelete();
                $table->unique(['field_id', 'entity_id']);
            });
        }
    }
};
