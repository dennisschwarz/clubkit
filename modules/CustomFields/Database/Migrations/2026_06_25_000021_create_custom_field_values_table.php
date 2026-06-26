<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_field_values')) {
            return;
        }

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();

            // Welches Feld?
            $table->unsignedBigInteger('field_id');

            // ID der Entität (Mitglied, Team, …) – kein typisierter FK, da polymorphisch
            $table->unsignedBigInteger('entity_id');

            // Gespeicherter Wert als Text (Zahlen, Daten etc. werden als String gespeichert)
            $table->text('value')->nullable();

            $table->timestamps();

            // Pro Feld kann jede Entität genau einen Wert haben
            $table->unique(['field_id', 'entity_id']);

            $table->foreign('field_id')
                  ->references('id')->on('custom_field_definitions')
                  ->cascadeOnDelete();

            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
