<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fügt das Setting 'registration_enabled' mit Standardwert '0' ein.
     * updateOrInsert stellt sicher, dass vorhandene Werte NICHT überschrieben werden.
     */
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'registration_enabled'],
            ['value' => '0']
        );
    }

    /**
     * Entfernt das Setting wieder.
     */
    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'registration_enabled')->delete();
    }
};
