<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hasColumn()-Guards verhindern den "Duplicate column"-Fehler
        // falls die Migration ein zweites Mal läuft (z. B. nach Modul-Reinstall)
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'gender')) {
                $table->string('gender', 20)->nullable()->after('date_of_birth');
            }

            if (!Schema::hasColumn('members', 'eligible_to_play')) {
                $table->boolean('eligible_to_play')->default(false)->after('gender');
            }
        });
    }

    public function down(): void
    {
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
