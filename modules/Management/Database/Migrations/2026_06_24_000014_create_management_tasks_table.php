<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_tasks')) {
            return;
        }

        Schema::create('management_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Optionale Beschreibung für die Aufgabe
            $table->text('description')->nullable();
            // Audit: Wer hat die Aufgabe angelegt?
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_tasks');
    }
};
