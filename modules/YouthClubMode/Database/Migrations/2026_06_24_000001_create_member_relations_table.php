<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_relations')) return;

        Schema::create('member_relations', function (Blueprint $table) {
            $table->id();

            // Elternteil (bei 'father'/'mother') oder erster Geschwisterteil (bei 'sibling')
            $table->foreignId('primary_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // Kind (bei 'father'/'mother') oder zweiter Geschwisterteil (bei 'sibling')
            $table->foreignId('secondary_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // 'father' | 'mother' | 'sibling'
            $table->string('relationship', 20);

            // Wer hat den Eintrag angelegt (System-Nutzer, nullable)
            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // Kein identisches Duplikat (primary, secondary, relationship)
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
