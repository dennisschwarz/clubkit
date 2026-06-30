<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER migration for existing databases.
 *
 * Changes import_sessions.created_by from CASCADE to SET NULL.
 * The original migration (000010) already contains the correct definition,
 * but the hasTable() guard prevents it from re-running on existing databases.
 *
 * Guard: skips databases where the column is already nullable.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('import_sessions')) return;

        // Check whether the column is already nullable → already correct
        $columns = Schema::getColumns('import_sessions');
        foreach ($columns as $col) {
            if ($col['name'] === 'created_by' && $col['nullable'] === true) {
                return; // Already correct, nothing to do
            }
        }

        Schema::table('import_sessions', function (Blueprint $table) {
            // Drop the FK constraint using Laravel's generated name
            $table->dropForeign(['created_by']);
            // Change the column to nullable
            $table->foreignId('created_by')->nullable()->change();
            // Re-add the FK with nullOnDelete
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('import_sessions')) return;

        Schema::table('import_sessions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
