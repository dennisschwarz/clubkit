<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the custom_field_values table.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('custom_field_values')) {
            return;
        }

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();

            // Which field definition this value belongs to
            $table->unsignedBigInteger('field_id');

            // ID of the entity (member, team, …) – no typed FK as the relation is polymorphic
            $table->unsignedBigInteger('entity_id');

            // Stored value as text (numbers, dates etc. are stored as strings)
            $table->text('value')->nullable();

            $table->timestamps();

            // Each entity can have exactly one value per field
            $table->unique(['field_id', 'entity_id']);

            $table->foreign('field_id')
                  ->references('id')->on('custom_field_definitions')
                  ->cascadeOnDelete();

            $table->index('entity_id');
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
