<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt created_by zur members-Tabelle hinzu.
 *
 * Guard: Spalte wird nur hinzugefügt wenn sie noch nicht existiert
 * (idempotent bei mehrfachem migrate).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('members') || Schema::hasColumn('members', 'created_by')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('created_by')
                  ->nullable()
                  ->after('profile_image')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('members') || !Schema::hasColumn('members', 'created_by')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
