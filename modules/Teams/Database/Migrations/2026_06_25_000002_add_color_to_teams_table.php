<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('teams')) {
            return;
        }
        if (Schema::hasColumn('teams', 'color')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            // Teamfarbe als Schlüssel aus der vordefinierten Palette (z.B. 'blue', 'red')
            $table->string('color', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
