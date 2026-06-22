<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Externe Verbands-IDs: DFBnet, Handball, usw.
        // Entkoppelt von members – erweiterbar für beliebige Sportarten / Verbände
        Schema::create('external_ids', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('federation');               // "dfbnet", "handball_net", "flvw"
            $table->string('external_id');              // Passnummer / ID beim Verband
            $table->timestamps();

            $table->unique(['member_id', 'federation']); // pro Verband 1 ID pro Member
            $table->index(['federation', 'external_id']); // schneller Lookup beim Import
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_ids');
    }
};
