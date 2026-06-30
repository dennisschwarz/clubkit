<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the custom_field_definitions table.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('custom_field_definitions')) {
            return;
        }

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();

            // Which entity type this field applies to ('member', 'team', 'event', …)
            $table->string('object_type', 50);

            // Human-readable display name (e.g. "Jersey Size")
            $table->string('label', 100);

            // Machine-readable key, unique per object_type
            $table->string('slug', 100);

            // Field type: 'text'|'textarea'|'number'|'decimal'|'select'|'checkbox'|'date'|'email'|'phone'|'url'|'whatsapp'
            $table->string('field_type', 20);

            // Option list for field_type='select' (JSON array)
            $table->json('options')->nullable();

            // Optional placeholder text shown in the input
            $table->string('placeholder', 200)->nullable();

            // Whether the field is mandatory
            $table->boolean('is_required')->default(false);

            // Display order within the object type
            $table->unsignedInteger('sort_order')->default(0);

            // User who created this definition
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            // Slug must be unique per object type
            $table->unique(['object_type', 'slug']);

            // Fast lookup by object type + sort order
            $table->index(['object_type', 'sort_order']);

            $table->foreign('created_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
    }
};
