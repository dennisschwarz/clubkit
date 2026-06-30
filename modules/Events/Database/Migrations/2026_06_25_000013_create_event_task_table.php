<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_task pivot table (event ↔ management task).
 *
 * task_id has no DB-level FK because Management is an optional module
 * (not in Events' requires[]). Deinstalling Management must not break
 * existing event records. The relation is enforced at the application
 * layer via Event::hasTaskAssigned() and Event::tasks().
 *
 * deadline_at semantics:
 *   NULL              → event-day task (no specific deadline)
 *   SET to a datetime → preparation task with a concrete deadline
 *
 * The completed flag is toggled via AJAX (EventController::completeTask).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('event_task')) {
            return;
        }

        Schema::create('event_task', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('task_id');        // No FK — Management module is optional (REGEL 13)
            $table->text('notes')->nullable();            // Task-specific note for this event
            $table->boolean('completed')->default(false); // Completion flag, toggled via AJAX
            $table->dateTime('deadline_at')->nullable();  // NULL = event-day task; SET = preparation task
            $table->timestamps();

            $table->foreign('event_id')
                  ->references('id')->on('events')
                  ->cascadeOnDelete();

            $table->unique(['event_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task');
    }
};
