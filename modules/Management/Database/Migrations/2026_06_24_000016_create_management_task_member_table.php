<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_task_member pivot table.
     *
     * member_id has no DB-level FK (cross-module dependency: Management migrations
     * run alphabetically before Members). Referential integrity is enforced at the
     * application level. Identical pattern to management_task_team and
     * management_function_team.
     */
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
            // No DB-level FK on member_id — Management is independent of Members.
            $table->unsignedBigInteger('member_id');
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'member_id']);
            $table->index('member_id');
        });
    }

    /**
     * Drop the management_task_member table.
     */
    public function down(): void
    {
        Schema::dropIfExists('management_task_member');
    }
};
