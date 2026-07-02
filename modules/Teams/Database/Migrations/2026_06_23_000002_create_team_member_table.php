<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the team_member pivot table.
 *
 * A member may belong to a team exactly once (unique constraint on team_id + member_id).
 * Both FKs cascade on delete so orphan rows are never left behind.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        if (Schema::hasTable('team_member')) return;

        Schema::create('team_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')
                  ->constrained('teams')
                  ->onDelete('cascade');
            $table->foreignId('member_id')
                  ->constrained('members')
                  ->onDelete('cascade');
            $table->unsignedSmallInteger('squad_number')->nullable();
            $table->timestamp('joined_at')->nullable()->comment('Date the member joined this team roster.');
            $table->timestamps();

            // A member may only belong to a team once
            $table->unique(['team_id', 'member_id']);
        });
    }

    /** @return void */
    public function down(): void
    {
        Schema::dropIfExists('team_member');
    }
};
