<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds gender and eligible_to_play columns to the members table.
     *
     * hasTable() guard prevents execution when members does not yet exist
     * (e.g. when running migrations in isolation during module re-install).
     * hasColumn() guards prevent a "Duplicate column" error if the migration
     * runs more than once.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'gender')) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            }

            if (!Schema::hasColumn('members', 'eligible_to_play')) {
                $table->boolean('eligible_to_play')->default(false)->after('gender');
            }
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            if (Schema::hasColumn('members', 'eligible_to_play')) {
                $table->dropColumn('eligible_to_play');
            }

            if (Schema::hasColumn('members', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
