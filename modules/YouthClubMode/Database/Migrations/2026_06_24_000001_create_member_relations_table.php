<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the member_relations table.
 *
 * Stores family relationships between two club members in canonical form:
 *   primary_member_id   = parent (or lower-ID sibling)
 *   secondary_member_id = child  (or higher-ID sibling)
 *   relationship        = 'father' | 'mother' | 'sibling'
 *
 * cascadeOnDelete on both member FKs: a relation without either member is meaningless.
 * created_by uses nullOnDelete so deleting a user does not erase relation history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_relations')) {
            return;
        }

        Schema::create('member_relations', function (Blueprint $table) {
            $table->id();

            // Parent (for 'father'/'mother') or canonical left-side sibling (for 'sibling')
            $table->foreignId('primary_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // Child (for 'father'/'mother') or canonical right-side sibling (for 'sibling')
            $table->foreignId('secondary_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // Relationship type: 'father' | 'mother' | 'sibling'
            $table->string('relationship', 20);

            // Who created this record (nullable – deleting a user must not erase relations)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // No identical duplicates (primary, secondary, relationship)
            $table->unique(
                ['primary_member_id', 'secondary_member_id', 'relationship'],
                'uniq_member_relation'
            );

            $table->index('primary_member_id',   'idx_primary');
            $table->index('secondary_member_id', 'idx_secondary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_relations');
    }
};
