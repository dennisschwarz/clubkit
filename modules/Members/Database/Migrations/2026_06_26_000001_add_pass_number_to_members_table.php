<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the pass_number column with an index to the members table.
     *
     * hasTable() guard prevents execution when members does not yet exist.
     * hasColumn() guard prevents a "Duplicate column" error on repeated runs.
     *
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('members')) {
            return;
        }

        if (! Schema::hasColumn('members', 'pass_number')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('pass_number', 20)->nullable()->after('status');
                $table->index('pass_number');
            });
        }
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('members') || ! Schema::hasColumn('members', 'pass_number')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['pass_number']);
            $table->dropColumn('pass_number');
        });
    }
};
