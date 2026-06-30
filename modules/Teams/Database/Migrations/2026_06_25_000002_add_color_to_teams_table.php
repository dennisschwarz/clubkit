<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the color column to the teams table.
 *
 * Stored as a color-key slug from the predefined palette (e.g. 'blue', 'red').
 * Must match a --ck-team-* CSS token defined in base.css.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }
        if (Schema::hasColumn('teams', 'color')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('name');
        });
    }

    /** @return void */
    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
