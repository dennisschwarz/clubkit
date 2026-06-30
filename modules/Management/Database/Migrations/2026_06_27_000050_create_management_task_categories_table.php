<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the management_task_categories table.
 *
 * Categories are optional groupings for management tasks.
 * A task may belong to at most one category (nullable FK in management_tasks).
 * created_by uses nullOnDelete so the category is preserved when the user is deleted.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_task_categories')) {
            return;
        }

        Schema::create('management_task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('created_by');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('management_task_categories');
    }
};
