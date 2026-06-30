<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_categories')) {
            return;
        }

        // A category always belongs to exactly one transaction type.
        // Examples: income → "Mitgliedsbeiträge", "Spende", "Verkauf"
        //           expense → "Anschaffung", "Spielbetrieb", "Strafe"
        Schema::create('treasury_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);

            // income or expense — never 'both'
            $table->enum('transaction_type', ['income', 'expense']);

            // Badge colour token matching the <x-ck-badge color="..."> prop
            $table->string('color', 20)->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('transaction_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_categories');
    }
};
