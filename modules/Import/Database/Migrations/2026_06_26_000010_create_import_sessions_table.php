<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the import_sessions table if it does not already exist.
     *
     * Sessions are identified by UUID, expire after 2 hours, and store
     * column headers, raw rows, samples, mapping, and processed rows as JSON.
     *
     * @return void
     */
    public function up(): void
    {
        if (Schema::hasTable('import_sessions')) return;

        Schema::create('import_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // nullable + nullOnDelete: session data is preserved when the user is deleted.
            // Import sessions have no business relevance after completion, but nullOnDelete
            // is consistent across all modules and prevents unexpected data loss.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 50);           // 'dfbnet', 'nuliga', ...
            $table->string('filename');
            $table->json('column_headers');          // ['Name Künstlername', 'Vorname Rufname', ...]
            $table->json('raw_rows');                // 2D array of all CSV rows
            $table->json('samples');                 // ['column' => ['val1','val2','val3']]
            $table->json('mapping')->nullable();     // ['column' => 'last_name'], set in step 2
            $table->json('processed_rows')->nullable(); // with status + diff, set in step 2
            $table->timestamp('expires_at');         // now + 2h
            $table->timestamps();
        });
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
