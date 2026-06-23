<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('members')) return;
        if (Schema::hasColumn('members', 'profile_image')) return;

        Schema::table('members', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('eligible_to_play');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('members', 'profile_image')) return;

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }
};
