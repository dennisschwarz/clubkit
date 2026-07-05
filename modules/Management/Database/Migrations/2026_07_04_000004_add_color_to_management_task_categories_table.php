<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a colour column to management_task_categories.
 *
 * Colour slugs: blue | green | amber | red | orange | purple | pink | teal | navy | slate | gray
 *   Same system used by event_task_categories and Teams section colours.
 *
 * Nullable: existing categories retain their data without a colour assigned.
 *   The colour is optional in both the store and update requests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_task_categories')) {
            return;
        }

        if (Schema::hasColumn('management_task_categories', 'color')) {
            return;
        }

        Schema::table('management_task_categories', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_task_categories')) {
            return;
        }

        Schema::table('management_task_categories', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
