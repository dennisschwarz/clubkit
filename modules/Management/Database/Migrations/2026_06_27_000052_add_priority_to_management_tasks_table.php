<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the priority column to management_tasks.
 *
 * Allowed values: 'normal' | 'important' | 'critical' (see ManagementTask::PRIORITIES).
 * Defaults to 'normal'.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('management_tasks')) {
            return;
        }
        if (Schema::hasColumn('management_tasks', 'priority')) {
            return;
        }

        Schema::table('management_tasks', function (Blueprint $table) {
            $table->string('priority', 20)
                  ->default('normal')
                  ->after('category_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasTable('management_tasks')) {
            return;
        }

        Schema::table('management_tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }
};
