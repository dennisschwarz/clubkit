<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_external_ids')) return;

        Schema::create('member_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('source', 50);       // 'dfbnet', 'nuliga', ...
            $table->string('external_id', 50);  // Passnummer, NuLiga-ID, ...
            $table->date('imported_on');
            $table->timestamps();

            // Ein Member kann pro Quelle nur eine externe ID haben
            $table->unique(['member_id', 'source']);
            $table->index(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_external_ids');
    }
};
