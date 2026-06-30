<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional user_id foreign key to the members table for portal access.
     *
     * Guard: only adds the column when it does not already exist.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasColumn('members', 'user_id')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            // Optional link to a User account for portal access.
            // null = member has no login; set = member can log in as this user.
            // The unique constraint enforces one login per member.
            $table->foreignId('user_id')
                  ->nullable()
                  ->unique()
                  ->after('id')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if (! Schema::hasColumn('members', 'user_id')) {
            return;
        }

        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
