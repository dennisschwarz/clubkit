<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the created_by foreign key to the members table.
 *
 * Guard: the column is only added when it does not already exist
 * (idempotent on repeated migrate runs).
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('members') || Schema::hasColumn('members', 'created_by')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('profile_image')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable('members') || !Schema::hasColumn('members', 'created_by')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
