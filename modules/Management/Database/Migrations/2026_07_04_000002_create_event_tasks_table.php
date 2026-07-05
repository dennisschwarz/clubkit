<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_tasks table.
 *
 * Replaces the former event_task pivot (Events module) with a full entity.
 *
 * An event task is either:
 *   - Imported from the global library: template_id references management_tasks.id
 *   - Created directly on the event:    template_id is NULL
 *
 * In both cases name and priority are stored locally, so the event task is
 * fully self-contained. Deleting the global template sets template_id to NULL
 * but preserves the event task data (name was copied at import time).
 *
 * Key differences from the old event_task pivot:
 *   - Full entity with own id — addressable directly (no composite keys needed)
 *   - name and priority columns stored locally (not resolved from management_tasks at runtime)
 *   - sort_order for user-defined ordering within a category
 *   - category_id points to event_task_categories (event-local scope)
 *   - template_id is a soft FK with nullOnDelete (preserves data on template deletion)
 *
 * deadline_at semantics:
 *   NULL  → event-day task (no specific deadline, visible on the tasks tab)
 *   SET   → preparation task with a concrete deadline before the event
 *
 * Guards: events and event_task_categories tables must exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('event_task_categories')) {
            return;
        }

        if (Schema::hasTable('event_tasks')) {
            return;
        }

        Schema::create('event_tasks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete();

            // Nullable: tasks without a category appear in the "Allgemein" section.
            // SET NULL on category delete: the task is preserved, just uncategorised.
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('event_task_categories')
                  ->nullOnDelete();

            // Soft reference to management_tasks (global task library).
            // DB-level FK with nullOnDelete: deleting a template preserves the event task.
            // NULL = task was created directly on this event (not imported from the library).
            $table->unsignedBigInteger('template_id')->nullable();
            $table->foreign('template_id')
                  ->references('id')->on('management_tasks')
                  ->nullOnDelete();

            $table->string('name', 200);
            $table->enum('priority', ['normal', 'important', 'critical'])->default('normal');
            $table->unsignedInteger('sort_order')->default(0);

            // NULL = event-day task; SET = preparation task with a concrete deadline.
            $table->dateTime('deadline_at')->nullable();

            $table->boolean('completed')->default(false);
            $table->text('notes')->nullable();

            // Nullable: creator account removal does not cascade to event tasks.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Composite index: covers all sorted task lookups per event and category.
            $table->index(['event_id', 'category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_tasks');
    }
};
