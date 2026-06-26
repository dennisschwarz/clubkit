<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: nur ausführen wenn teams existiert, aber Spalten noch fehlen
        if (!Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            if (!Schema::hasColumn('teams', 'is_competition')) {
                $table->boolean('is_competition')->default(false)->after('name');
            }
            if (!Schema::hasColumn('teams', 'eligible_only')) {
                $table->boolean('eligible_only')->default(false)->after('is_competition');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['is_competition', 'eligible_only']);
        });
    }
};
