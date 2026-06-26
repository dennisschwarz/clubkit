<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('import_sessions')) return;

        Schema::create('import_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('source', 50);           // 'dfbnet', 'nuliga', ...
            $table->string('filename');
            $table->json('column_headers');          // ['Name Künstlername', 'Vorname Rufname', ...]
            $table->json('raw_rows');                // 2D-Array aller CSV-Zeilen
            $table->json('samples');                 // ['Spaltenname' => ['val1','val2','val3']]
            $table->json('mapping')->nullable();     // ['Spaltenname' => 'last_name'], gesetzt in Stufe 2
            $table->json('processed_rows')->nullable(); // mit status + diff, gesetzt in Stufe 2
            $table->timestamp('expires_at');         // now + 2h
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
