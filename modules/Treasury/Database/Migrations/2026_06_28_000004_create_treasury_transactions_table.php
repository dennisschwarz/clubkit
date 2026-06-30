<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the treasury_transactions table.
 *
 * Transactions are always positive amounts; the type column (income/expense)
 * determines the sign on the account balance.
 *
 * event_id and task_id are intentionally stored without a database-level FK
 * constraint. Events and Management are optional modules that may not be
 * installed. A hard FK would cause this migration to fail when those tables
 * do not yet exist. The relationships are enforced at the application layer.
 *
 * member_id has a proper FK constraint because Members is a declared
 * hard dependency in module.json requires[].
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_transactions')) {
            return;
        }

        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('account_id')
                  ->constrained('treasury_accounts')
                  ->cascadeOnDelete();

            // Category may be null when the transaction has no classification yet.
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('treasury_categories')
                  ->nullOnDelete();

            // income = Einnahme, expense = Ausgabe
            $table->enum('type', ['income', 'expense']);

            // Amount is always positive; type determines the sign on the balance.
            $table->decimal('amount', 10, 2);

            $table->string('description', 500);
            $table->date('transaction_date');
            $table->string('reference_number', 100)->nullable();

            // Members module is a hard dependency — FK constraint is safe.
            $table->unsignedBigInteger('member_id')->nullable();
            $table->foreign('member_id')
                  ->references('id')
                  ->on('members')
                  ->nullOnDelete();

            // Optional-module columns — no FK constraints (REGEL 13).
            $table->unsignedBigInteger('event_id')->nullable();  // Events module
            $table->unsignedBigInteger('task_id')->nullable();   // Management module

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['account_id', 'transaction_date']);
            $table->index('type');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
