<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Benennt event_organizer → event_assignments um.
 *
 * "Organizer" war zu eng gedacht. Das Konzept ist ein
 * einmaliges Sonder-Assignment (Person + Beschreibung) nur für
 * diesen einen Termin. Kein Bezug zu Management-Funktionen oder Aufgaben.
 *
 * Gleichzeitig: management_function_name → description
 * (Die Beschreibung ist kein Funktionsname mehr, sondern eine
 *  freie Beschreibung der einmaligen Aufgabe / des Einsatzes.)
 *
 * Guards: Quell-Tabelle muss existieren, Ziel-Tabelle darf noch nicht existieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bereits umbenannt → nichts tun
        if (Schema::hasTable('event_assignments')) {
            return;
        }
        if (!Schema::hasTable('event_organizer')) {
            return;
        }

        Schema::rename('event_organizer', 'event_assignments');

        if (Schema::hasColumn('event_assignments', 'management_function_name')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->renameColumn('management_function_name', 'description');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('event_assignments')) {
            return;
        }

        if (Schema::hasColumn('event_assignments', 'description')) {
            Schema::table('event_assignments', function (Blueprint $table) {
                $table->renameColumn('description', 'management_function_name');
            });
        }

        Schema::rename('event_assignments', 'event_organizer');
    }
};
