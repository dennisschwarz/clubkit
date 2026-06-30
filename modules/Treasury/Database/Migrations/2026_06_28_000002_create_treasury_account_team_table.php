<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_account_team')) {
            return;
        }

        // Pivot: which teams may access a team_restricted treasury account.
        // An account with visibility='public' ignores entries in this table.
        //
        // team_id is stored WITHOUT a DB-level FK constraint (REGEL 13):
        // Teams is an optional soft-dependency of Treasury. A hard FK would
        // cause this migration to fail when Treasury is installed without Teams,
        // and would prevent Teams from being deinstalled while treasury accounts
        // with team restrictions exist.
        // Referential integrity is handled at the application layer.
        Schema::create('treasury_account_team', function (Blueprint $table) {
            $table->foreignId('treasury_account_id')
                  ->constrained('treasury_accounts')
                  ->cascadeOnDelete();

            // No FK constraint — Teams is an optional module.
            $table->unsignedBigInteger('team_id');

            $table->primary(['treasury_account_id', 'team_id']);

            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_account_team');
    }
};
