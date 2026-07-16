<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: table may not exist if Management module is not installed.
        if (! Schema::hasTable('management_functions')) {
            return;
        }

        // Guard: column may already exist (e.g. re-run after partial failure).
        if (Schema::hasColumn('management_functions', 'description')) {
            return;
        }

        Schema::table('management_functions', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_functions')) {
            return;
        }

        if (! Schema::hasColumn('management_functions', 'description')) {
            return;
        }

        Schema::table('management_functions', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
