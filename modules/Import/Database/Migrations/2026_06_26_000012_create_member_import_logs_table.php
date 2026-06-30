<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the member_import_logs table for import audit records.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('member_import_logs')) return;

        Schema::create('member_import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50);
            $table->string('filename');
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            // nullable + nullOnDelete: audit logs must NEVER be cascade-deleted.
            // Removing a user account must not destroy import history.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('member_import_logs');
    }
};
