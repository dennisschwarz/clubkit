<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_task_meta')) {
            return;
        }

        // Extension table: adds treasury meaning to an existing management_task.
        // A task with a row here is treated as a "contribution task" in the treasury.
        // One task can only belong to one treasury account at a time.
        Schema::create('treasury_task_meta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                  ->unique()
                  ->constrained('management_tasks')
                  ->cascadeOnDelete();

            $table->foreignId('account_id')
                  ->constrained('treasury_accounts')
                  ->cascadeOnDelete();

            // Optional default amount applied when adding new member payment entries
            $table->decimal('default_amount', 10, 2)->nullable();

            $table->date('due_date')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_task_meta');
    }
};
