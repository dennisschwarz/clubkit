<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Insert extended appearance settings.
     * Uses updateOrInsert so existing values are NOT overwritten.
     * Guard: returns early when the settings table does not exist.
     *
     * @return void
     */
    public function up(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        $defaults = [
            // Brand bar
            'brand_bar_text'      => '#ffffff',
            'brand_bar_hover'     => '#e2e8f0',
            // Navigation bar
            'nav_bar_show_emojis' => '1',
            'nav_bar_bg'          => '#132238',
            'nav_bar_text'        => '#a0aec0',
            'nav_bar_hover'       => '#e2e8f0',
            'nav_bar_active_bar'  => '#60a5fa',
            'nav_bar_font_size'   => '14',
            // Sub-tab bar
            'subtab_show_emojis'  => '1',
            'subtab_bg'           => '#ffffff',
            'subtab_text'         => '#64748b',
            'subtab_hover'        => '#1e293b',
            'subtab_active_bar'   => '#60a5fa',
            'subtab_font_size'    => '14',
        ];

        foreach ($defaults as $key => $value) {
            // Insert only when the key does not yet exist
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }

    /**
     * Remove the extended appearance settings added by this migration.
     *
     * @return void
     */
    public function down(): void
    {
        if (!Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->whereIn('key', [
            'brand_bar_text', 'brand_bar_hover',
            'nav_bar_show_emojis', 'nav_bar_bg', 'nav_bar_text',
            'nav_bar_hover', 'nav_bar_active_bar', 'nav_bar_font_size',
            'subtab_show_emojis', 'subtab_bg', 'subtab_text',
            'subtab_hover', 'subtab_active_bar', 'subtab_font_size',
        ])->delete();
    }
};
