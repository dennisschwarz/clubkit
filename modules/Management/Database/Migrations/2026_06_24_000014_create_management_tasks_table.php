<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_tasks table.
     *
     * description is optional. created_by uses nullOnDelete so task records
     * are preserved when the user is deleted.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_tasks')) {
            return;
        }

        Schema::create('management_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Optional description for the task
            $table->text('description')->nullable();
            // Audit: who created this task?
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('created_by');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('management_tasks');
    }
};
