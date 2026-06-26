<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('custom_field_definitions')) {
            return;
        }

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();

            // Für welches Objekt gilt dieses Feld? ('member', 'team', 'event', …)
            $table->string('object_type', 50);

            // Anzeigename (z.B. "Trikotgröße")
            $table->string('label', 100);

            // Maschinenlesbarer Schlüssel, eindeutig pro object_type
            $table->string('slug', 100);

            // Feldtyp: 'text'|'textarea'|'number'|'decimal'|'select'|'checkbox'|'date'|'email'|'phone'|'url'|'whatsapp'
            $table->string('field_type', 20);

            // Optionen für field_type='select' (JSON-Array)
            $table->json('options')->nullable();

            // Optionaler Platzhaltertext
            $table->string('placeholder', 200)->nullable();

            // Pflichtfeld?
            $table->boolean('is_required')->default(false);

            // Reihenfolge innerhalb des Objekt-Typs
            $table->unsignedInteger('sort_order')->default(0);

            // Wer hat das Feld angelegt?
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Slug ist eindeutig pro Objekt-Typ
            $table->unique(['object_type', 'slug']);

            // Schneller Zugriff nach Objekt-Typ + Sortierung
            $table->index(['object_type', 'sort_order']);

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
