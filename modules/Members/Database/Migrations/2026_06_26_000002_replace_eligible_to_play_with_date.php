<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ersetzt eligible_to_play (boolean) durch eligible_to_play_date (date nullable).
 *
 * Datenmigration: bestehende Mitglieder mit eligible_to_play = 1
 * erhalten das heutige Datum als eligible_to_play_date, damit
 * der Accessor sie weiterhin als spielberechtigt ausgibt.
 *
 * Mitglieder mit eligible_to_play = 0 erhalten NULL → nicht spielberechtigt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'eligible_to_play')) return;

        Schema::table('members', function (Blueprint $table) {
            // Neue Spalte neben der alten anlegen
            $table->date('eligible_to_play_date')->nullable()->after('eligible_to_play');
        });

        // Datenmigration: eligible_to_play = 1 → today als Startdatum übernehmen
        DB::table('members')
            ->where('eligible_to_play', true)
            ->update(['eligible_to_play_date' => now()->toDateString()]);

        Schema::table('members', function (Blueprint $table) {
            // Alte Boolean-Spalte entfernen
            $table->dropColumn('eligible_to_play');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('members', 'eligible_to_play_date')) return;

        Schema::table('members', function (Blueprint $table) {
            $table->boolean('eligible_to_play')->default(false)->after('gender');
        });

        // Rückwärtsmigration: Datum vorhanden → eligible_to_play = 1
        DB::table('members')
            ->whereNotNull('eligible_to_play_date')
            ->update(['eligible_to_play' => true]);

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('eligible_to_play_date');
        });
    }
};
