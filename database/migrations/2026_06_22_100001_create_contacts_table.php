<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();        // Kontakt-E-Mail ≠ Login-E-Mail
            $table->string('street')->nullable();
            $table->string('street_number', 10)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
