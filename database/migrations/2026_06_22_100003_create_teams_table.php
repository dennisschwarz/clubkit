<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->string('name');                     // "D1 Liga", "Probetraining"
            $table->string('slug');                     // "d1", "d2", "probe"
            $table->string('color', 7)->default('#1a6fc4');
            $table->string('type')->default('regular'); // regular | trial | virtual
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['season_id', 'slug']);
            $table->index(['season_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
