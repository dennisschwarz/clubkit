<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the category_id FK column to management_tasks.
 *
 * ON DELETE SET NULL: when a category is deleted, its tasks retain their data
 * but lose their category assignment.
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
        if (Schema::hasColumn('management_tasks', 'category_id')) {
            return;
        }

        Schema::table('management_tasks', function (Blueprint $table) {
            $table->foreignId('category_id')
                  ->nullable()
                  ->after('description')
                  ->constrained('management_task_categories')
                  ->nullOnDelete();
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
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
