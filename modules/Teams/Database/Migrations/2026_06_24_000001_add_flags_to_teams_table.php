<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds is_competition and eligible_only boolean flags to the teams table.
 *
 * is_competition: marks a team as a competitive team (league/cup).
 * eligible_only:  restricts membership to players with a valid playing eligibility.
 */
return new class extends Migration
{
    /** @return void */
    public function up(): void
    {
        // Guard: only run when teams exists but the columns are still missing
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            if (! Schema::hasColumn('teams', 'is_competition')) {
                $table->boolean('is_competition')->default(false)->after('name');
            }
            if (! Schema::hasColumn('teams', 'eligible_only')) {
                $table->boolean('eligible_only')->default(false)->after('is_competition');
            }
        });
    }

    /** @return void */
    public function down(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['is_competition', 'eligible_only']);
        });
    }
};
