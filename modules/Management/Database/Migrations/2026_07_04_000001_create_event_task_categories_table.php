<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the event_task_categories table.
 *
 * Each category is scoped to exactly one event. Categories are displayed as
 * collapsible, colour-coded sections on the event detail tasks tab.
 *
 * Architecture note:
 *   Owned by the Management module: all task-related tables, controllers,
 *   and ViewComposers live in Management. event_id FK points to Events.
 *
 * Colour slugs: blue | green | amber | red | orange | purple | pink | teal | navy | slate | gray
 *   Shared system with Teams section colours and management_task_categories.
 *
 * sort_order: user-defined display order within the event tasks tab.
 *   Updated via the drag & drop reorder endpoint.
 *
 * Deleting a category sets category_id to NULL on all event_tasks rows
 *   via the DB ON DELETE SET NULL constraint on event_tasks.category_id.
 *   Tasks become "uncategorised" and appear in the "Allgemein" section.
 *
 * Guard: events table must exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('events')) {
            return;
        }

        if (Schema::hasTable('event_task_categories')) {
            return;
        }

        Schema::create('event_task_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                  ->constrained('events')
                  ->cascadeOnDelete();

            $table->string('name', 100);

            // One of: blue | green | amber | red | orange | purple | pink | teal | navy | slate | gray
            // Nullable: a category may be created without a colour first.
            $table->string('color', 20)->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            // Nullable: user records are not deleted when a creator account is removed.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Composite index: covers all sorted category lookups per event.
            $table->index(['event_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_task_categories');
    }
};
