<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an index on installed_modules.is_active.
 *
 * This column is queried on every request in ModuleLoader::boot()
 * to resolve the list of active modules. The index avoids a full table scan.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('installed_modules')) {
            return;
        }

        Schema::table('installed_modules', function (Blueprint $table) {
            // Named constraint so down() can drop it reliably by name.
            $table->index('is_active', 'installed_modules_is_active_index');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('installed_modules')) {
            return;
        }

        Schema::table('installed_modules', function (Blueprint $table) {
            $table->dropIndex('installed_modules_is_active_index');
        });
    }
};
