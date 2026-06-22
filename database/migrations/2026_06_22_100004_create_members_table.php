<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table): void {
            $table->id();

            // Wer ist diese Person? (1:1 – jede Person hat genau 1 Member-Datensatz)
            $table->foreignId('contact_id')->unique()->constrained()->restrictOnDelete();

            // Hat diese Person einen Login? (optional – nicht jedes Mitglied will einen Account)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Ist diese Person Sportler/Spieler?
            // Guardian-Funktionalität kommt im Junior-Modul via relationale Daten.
            $table->boolean('is_player')->default(true);

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
