<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('members', 'pass_number')) {
            Schema::table('members', function (Blueprint $table) {
                $table->string('pass_number', 20)->nullable()->after('status');
                $table->index('pass_number');
            });
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['pass_number']);
            $table->dropColumn('pass_number');
        });
    }
};
