<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_task_member')) {
            return;
        }

        Schema::create('management_task_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')
                  ->constrained('management_tasks')
                  ->cascadeOnDelete();
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'member_id']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_task_member');
    }
};
