<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ALTER migration for existing databases.
 *
 * Changes member_import_logs.created_by from CASCADE to SET NULL.
 * Audit logs must never be cascade-deleted when a user is removed.
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
        if (! Schema::hasTable('member_import_logs')) return;

        // Check whether the column is already nullable → already correct
        $columns = Schema::getColumns('member_import_logs');
        foreach ($columns as $col) {
            if ($col['name'] === 'created_by' && $col['nullable'] === true) {
                return; // Already correct, nothing to do
            }
        }

        Schema::table('member_import_logs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('member_import_logs')) return;

        Schema::table('member_import_logs', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->foreignId('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
