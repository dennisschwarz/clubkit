<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('management_functions')) {
            return;
        }

        Schema::create('management_functions', function (Blueprint $table) {
            $table->id();
            // Name ist NICHT unique: "Trainer" kann für D1 und D2 separat existieren
            $table->string('name', 100);
            // Audit: Wer hat die Funktion angelegt?
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
        Schema::dropIfExists('management_functions');
    }
};
