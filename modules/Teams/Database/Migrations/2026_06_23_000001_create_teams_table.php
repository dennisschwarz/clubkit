<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the teams table.
 *
 * The season column is stored as a plain string (e.g. "2026/27")
 * and will be replaced by a season_id foreign key once the Seasons module is installed.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        if (Schema::hasTable('teams')) return;

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('season')->nullable()->comment('e.g. 2026/27 – will be replaced by season_id FK later');
            $table->string('league')->nullable();
            $table->string('age_class')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /** @return void */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
