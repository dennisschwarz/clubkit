<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_accounts')) {
            return;
        }

        Schema::create('treasury_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();

            // Self-referential: null = top-level account, set = sub-account of parent
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('treasury_accounts')
                  ->nullOnDelete();

            // public  = visible to all users with treasury.view
            // team_restricted = only visible to team members of assigned teams
            $table->enum('visibility', ['public', 'team_restricted'])->default('public');

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index('visibility');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_accounts');
    }
};
