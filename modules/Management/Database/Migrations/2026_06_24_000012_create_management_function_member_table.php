<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_function_member pivot table.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_function_member')) {
            return;
        }

        Schema::create('management_function_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')
                  ->constrained('management_functions')
                  ->cascadeOnDelete();
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'member_id']);
            $table->index('member_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('management_function_member');
    }
};
