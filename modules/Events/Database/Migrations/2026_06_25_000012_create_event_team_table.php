<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_team pivot table (event ↔ team).
 *
 * team_id has no FK constraint because the Teams module is an optional soft-dependency.
 * Deinstalling Teams must not break existing event records.
 * Guard at the call site with class_exists(\Modules\Teams\Models\Team::class).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_team')) {
            return;
        }

        Schema::create('event_team', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->foreign('event_id')
                  ->references('id')->on('events')
                  ->cascadeOnDelete();

            $table->unique(['event_id', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_team');
    }
};
