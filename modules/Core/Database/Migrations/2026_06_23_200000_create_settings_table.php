<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the settings table and seed default values.
     * Guard: returns early if the table already exists.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            return;
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();   // Unique setting key (e.g. "header_bg")
            $table->text('value')->nullable();   // Stored value
        });

        // Seed default values on first creation
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
     * Drop the settings table.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
