<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_parents')) return;

        Schema::create('member_parents', function (Blueprint $table) {
            $table->id();

            // The child (player)
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // The guardian (also a member entry)
            $table->foreignId('parent_member_id')
                  ->constrained('members')
                  ->cascadeOnDelete();

            // 'father' or 'mother'
            $table->enum('relationship', ['father', 'mother']);

            $table->timestamps();

            // Max. 1 father and 1 mother per child
            $table->unique(['member_id', 'relationship'], 'uniq_member_relationship');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_parents');
    }
};
