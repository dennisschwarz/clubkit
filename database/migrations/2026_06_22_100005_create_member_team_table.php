<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // n:m – Ein Member kann in mehreren Teams sein, ein Team hat mehrere Members
        Schema::create('member_team', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'team_id']); // kein doppeltes Eintragen
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_team');
    }
};
