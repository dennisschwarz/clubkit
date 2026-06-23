<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Settings-Tabelle anlegen.
     * Guard: wenn die Tabelle bereits existiert, nichts tun.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();   // Eindeutiger Schlüssel (z. B. "header_bg")
            $table->text('value')->nullable();   // Gespeicherter Wert
        });

        // Standardwerte beim ersten Anlegen setzen
        DB::table('settings')->insert([
            ['key' => 'club_name',    'value' => config('app.name', 'ClubKit')],
            ['key' => 'logo_path',    'value' => ''],
            ['key' => 'header_bg',    'value' => '#0a1628'],
            ['key' => 'header_hover', 'value' => '#1a2d4a'],
            ['key' => 'accent_color', 'value' => '#60a5fa'],
            ['key' => 'body_bg',      'value' => '#f0f3f8'],
        ]);
    }

    /**
     * Rollback: Tabelle löschen.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
