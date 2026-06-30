<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the eligible_to_play boolean column with eligible_to_play_date (date, nullable).
 *
 * Data migration: existing members with eligible_to_play = 1 receive today's date
 * as eligible_to_play_date so the accessor continues to return them as eligible.
 * Members with eligible_to_play = 0 receive NULL (not eligible).
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'eligible_to_play')) return;

        Schema::table('members', function (Blueprint $table) {
            // Add new date column next to the old boolean column
            $table->date('eligible_to_play_date')->nullable()->after('eligible_to_play');
        });

        // Data migration: eligible_to_play = 1 → carry today's date as start date
        DB::table('members')
            ->where('eligible_to_play', true)
            ->update(['eligible_to_play_date' => now()->toDateString()]);

        Schema::table('members', function (Blueprint $table) {
            // Remove the old boolean column
            $table->dropColumn('eligible_to_play');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasColumn('members', 'eligible_to_play_date')) return;

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('eligible_to_play')->default(false)->after('gender');
        });

        // Reverse migration: date present → eligible_to_play = 1
        DB::table('members')
            ->whereNotNull('eligible_to_play_date')
            ->update(['eligible_to_play' => true]);

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('eligible_to_play_date');
        });
    }
};
