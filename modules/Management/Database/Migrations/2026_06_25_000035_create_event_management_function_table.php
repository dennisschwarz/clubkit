<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Join table: Event <-> ManagementFunction.
 *
 * Only created when both source tables exist (events + management_functions).
 * Since Events migrations run before Management migrations, this guard is critical.
 *
 * Managed by the Management module (extends Events with function integration).
 * Dropped when the Management module is uninstalled.
 */
return new class extends Migration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (! Schema::hasTable('events') || ! Schema::hasTable('management_functions')) {
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

            // Nullable: function is assigned to an event first, member filled in later.
            // No DB-level FK — Events module does not depend on Members at the schema level.
            $table->unsignedBigInteger('member_id')->nullable();

            $table->primary(['event_id', 'management_function_id']);
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('event_management_function');
    }
};
