<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('teams') || Schema::hasColumn('teams', 'created_by')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('is_active')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('teams', 'created_by')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
