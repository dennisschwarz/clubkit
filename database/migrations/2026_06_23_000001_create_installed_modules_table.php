<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the installed_modules table.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('installed_modules')) {
            return;
        }

        Schema::create('installed_modules', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->string('version', 20)->default('1.0.0');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('installed_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }
};
