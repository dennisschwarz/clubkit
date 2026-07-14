<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_functions') || Schema::hasColumn('management_functions', 'description')) {
            return;
        }

        Schema::table('management_functions', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('management_functions')) {
            return;
        }

        Schema::table('management_functions', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
