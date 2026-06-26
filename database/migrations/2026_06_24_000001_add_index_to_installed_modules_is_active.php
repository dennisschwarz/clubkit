<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix 12: Index auf installed_modules.is_active
 * Diese Spalte wird bei jedem Request in ModuleLoader::boot() abgefragt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('installed_modules')) {
            return;
        }

        Schema::table('installed_modules', function (Blueprint $table) {
            // Prüfen ob Index bereits existiert, um doppelte Ausführung zu verhindern
            $table->index('is_active', 'installed_modules_is_active_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('installed_modules')) {
            return;
        }

        Schema::table('installed_modules', function (Blueprint $table) {
            $table->dropIndex('installed_modules_is_active_index');
        });
    }
};
