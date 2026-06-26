<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpfungs-Tabelle: Termin ↔ Vereinsfunktion.
 *
 * Wird nur angelegt wenn beide Quell-Tabellen existieren
 * (events + management_functions). Da Events-Migrationen früher
 * laufen als Management-Migrationen, ist dieser Guard kritisch.
 *
 * Wird im Management-Modul verwaltet (extends Events um Funktions-Integration).
 * Beim Deinstall des Management-Moduls wird diese Tabelle mitgedroppt.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('events') || !Schema::hasTable('management_functions')) {
            return;
        }
        if (Schema::hasTable('event_management_function')) {
            return;
        }

        Schema::create('event_management_function', function (Blueprint $table) {
            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete();

            $table->foreignId('management_function_id')
                  ->constrained('management_functions')
                  ->cascadeOnDelete();

            $table->primary(['event_id', 'management_function_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_management_function');
    }
};
