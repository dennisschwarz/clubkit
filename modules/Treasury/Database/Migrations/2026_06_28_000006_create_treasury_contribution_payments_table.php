<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('treasury_contribution_payments')) {
            return;
        }

        // Per-member payment tracking for contribution tasks.
        // A row exists for each member who is expected to pay for a given task.
        //   paid_at = null  → payment outstanding
        //   paid_at = <ts>  → payment received on that date
        // transaction_id is set when the Kassenwart books the payment in the treasury.
        Schema::create('treasury_contribution_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')
                  ->constrained('management_tasks')
                  ->cascadeOnDelete();

            // nullOnDelete: deleting a member must not erase financial history.
            // Contribution payment records are part of the financial audit trail
            // and must survive a member deletion. member_id becomes null to signal
            // the associated member no longer exists.
            $table->unsignedBigInteger('member_id')->nullable();
            $table->foreign('member_id')
                  ->references('id')
                  ->on('members')
                  ->nullOnDelete();

            // Amount this specific member owes (can differ from task default_amount).
            $table->decimal('amount', 10, 2);

            $table->timestamp('paid_at')->nullable();

            // Link to the treasury transaction created when this payment was booked.
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained('treasury_transactions')
                  ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            // One payment entry per member per task.
            // member_id is now nullable, so the unique constraint must allow nulls
            // — MySQL treats NULLs as distinct in unique indexes, which is correct here.
            $table->unique(['task_id', 'member_id']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_contribution_payments');
    }
};
