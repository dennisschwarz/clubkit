<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the management_functions table.
     *
     * name is NOT unique: "Trainer" can exist separately for D1 and D2.
     * created_by uses nullOnDelete so the function record is preserved when the user is deleted.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('management_functions')) {
            return;
        }

        Schema::create('management_functions', function (Blueprint $table) {
            $table->id();
            // name is NOT unique: the same role can exist for multiple teams
            $table->string('name', 100);
            // Audit: who created this function?
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
        Schema::dropIfExists('management_functions');
    }
};
