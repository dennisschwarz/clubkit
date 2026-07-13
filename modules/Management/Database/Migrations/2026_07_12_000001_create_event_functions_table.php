<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Event-scoped ad-hoc functions (Option C).
 *
 * Unlike management_functions (club-wide, reusable), event_functions are
 * created directly on a single event and cascade-deleted with it.
 *
 * No FK for member_id / created_by — Members module is optional (REGEL 13).
 * Managed by the Management module; dropped when Management is uninstalled.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        if (! Schema::hasTable('events') || Schema::hasTable('event_functions')) {
            return;
        }

        Schema::create('event_functions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete();

            $table->string('name');

            // No DB FK — Members module is optional (REGEL 13).
            $table->unsignedBigInteger('member_id')->nullable();

            // No DB FK — User may not exist on minimal installs.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
        });
    }

    /** @return void */
    public function down(): void
    {
        Schema::dropIfExists('event_functions');
    }
};
