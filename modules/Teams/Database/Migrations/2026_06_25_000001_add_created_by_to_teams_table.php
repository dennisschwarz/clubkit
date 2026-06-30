<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the created_by foreign key to the teams table.
 *
 * Nullable so that existing rows and seeded data without a creator are valid.
 * Set to null on user deletion (nullOnDelete) to avoid orphaned records.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        if (! Schema::hasTable('teams') || Schema::hasColumn('teams', 'created_by')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /** @return void */
    public function down(): void
    {
        if (! Schema::hasColumn('teams', 'created_by')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
