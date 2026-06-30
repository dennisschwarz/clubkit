<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create the Spatie ActivityLog v6 activity_log table.
 *
 * This migration must be published manually to database/migrations/
 * because spatie/laravel-activitylog v6 does NOT auto-register migrations
 * via loadMigrationsFrom() – unlike Spatie Permission.
 *
 * Without this migration the activity_log table is missing in test
 * environments (SQLite in-memory), causing QueryException on every
 * Model::create() that uses the LogsActivity trait.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable(config('activitylog.table_name', 'activity_log'))) {
            return;
        }

        Schema::connection(config('activitylog.database_connection'))
            ->create(
                config('activitylog.table_name', 'activity_log'),
                static function (Blueprint $table): void {
                    $table->id();
                    $table->string('log_name')->nullable()->index();
                    $table->text('description');
                    $table->nullableMorphs('subject', 'subject');
                    $table->nullableMorphs('causer', 'causer');
                    $table->json('properties')->nullable();
                    $table->json('attribute_changes')->nullable();
                    $table->string('event')->nullable();
                    $table->uuid('batch_uuid')->nullable();
                    $table->timestamps();
                }
            );
    }

    public function down(): void
    {
        Schema::dropIfExists(config('activitylog.table_name', 'activity_log'));
    }
};
