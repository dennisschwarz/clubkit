<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Treasury\Http\Requests\StoreContributionRequest;
use Modules\Treasury\Http\Requests\StorePaymentMemberRequest;
use Modules\Treasury\Models\TreasuryContributionPayment;
use Modules\Treasury\Models\TreasuryTaskMeta;
use Modules\Treasury\Models\TreasuryTransaction;

/**
 * Handles contribution task management within the Treasury module.
 *
 * A "contribution task" is a standard ManagementTask that has been linked
 * to a treasury account via a TreasuryTaskMeta record. Members can be
 * assigned to the task with an individual payment amount, and the Kassenwart
 * can mark each member's payment as received.
 *
 * When a payment is marked as paid, this controller creates a TreasuryTransaction
 * automatically and stores its ID on the payment row for traceability.
 * Both operations run in a DB transaction to prevent ledger inconsistencies.
 */
class TreasuryContributionController extends Controller
{
    /**
     * Links a management task to a treasury account (creates TreasuryTaskMeta).
     */
    public function store(StoreContributionRequest $request): RedirectResponse
    {
        $data               = $request->validated();
        $data['created_by'] = $request->user()->id;

        TreasuryTaskMeta::create($data);

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.contribution_assigned'));
    }

    /**
     * Unlinks a task from the treasury (removes TreasuryTaskMeta and its payments).
     *
     * Individual payment transactions already created are kept for audit purposes;
     * only the payment tracking rows and the meta record are removed.
     */
    public function destroy(TreasuryTaskMeta $taskMeta): RedirectResponse
    {
        // Detach payments (keep the actual transactions for audit)
        $taskMeta->payments()->update(['transaction_id' => null]);
        $taskMeta->payments()->delete();
        $taskMeta->delete();

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.contribution_removed'));
    }

    /**
     * Adds a member to the payment tracking list for a contribution task.
     */
    public function addMember(StorePaymentMemberRequest $request, TreasuryTaskMeta $taskMeta): RedirectResponse
    {
        $data = $request->validated();

        TreasuryContributionPayment::firstOrCreate(
            ['task_id' => $taskMeta->task_id, 'member_id' => $data['member_id']],
            [
                'amount'     => $data['amount'],
                'notes'      => $data['notes'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.payment_member_added'));
    }

    /**
     * Marks a member's payment as received and books a transaction.
     *
     * Both the transaction creation and the payment update run in a single
     * DB transaction to prevent a state where a booking exists without a
     * corresponding payment record (which would cause a double-booking on retry).
     */
    public function markPaid(TreasuryContributionPayment $payment, TreasuryTaskMeta $taskMeta): RedirectResponse
    {
        if ($payment->isPaid()) {
            return redirect()->back()->with('error', __('treasury.flash.payment_already_recorded'));
        }

        $member = $payment->member;

        DB::transaction(function () use ($payment, $taskMeta, $member): void {
            $transaction = TreasuryTransaction::create([
                'account_id'       => $taskMeta->account_id,
                'category_id'      => null,
                'type'             => 'income',
                'amount'           => $payment->amount,
                'description'      => $taskMeta->task->name . ' – ' . ($member?->last_name . ', ' . $member?->first_name),
                'transaction_date' => now()->toDateString(),
                'member_id'        => $payment->member_id,
                'task_id'          => $payment->task_id,
                'created_by'       => auth()->id(),
            ]);

            $payment->update([
                'paid_at'        => now(),
                'transaction_id' => $transaction->id,
            ]);
        });

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.payment_marked_paid'));
    }

    /**
     * Reverts a payment to unpaid status.
     *
     * The linked transaction is deleted to keep the treasury ledger consistent.
     * Both the transaction deletion and the payment update run in a single
     * DB transaction.
     */
    public function markUnpaid(TreasuryContributionPayment $payment, TreasuryTaskMeta $taskMeta): RedirectResponse
    {
        if (! $payment->isPaid()) {
            return redirect()->back()->with('error', __('treasury.flash.payment_already_pending'));
        }

        DB::transaction(function () use ($payment): void {
            if ($payment->transaction_id) {
                TreasuryTransaction::find($payment->transaction_id)?->delete();
            }

            $payment->update(['paid_at' => null, 'transaction_id' => null]);
        });

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.payment_reset'));
    }

    /**
     * Removes a member from the payment tracking list.
     *
     * Cannot remove paid entries; revert the payment first.
     */
    public function removeMember(TreasuryContributionPayment $payment, TreasuryTaskMeta $taskMeta): RedirectResponse
    {
        if ($payment->isPaid()) {
            return redirect()->back()
                ->with('error', __('treasury.flash.payment_member_paid_no_remove'));
        }

        $payment->delete();

        return redirect()->route('treasury.index', ['tab' => 'beitraege'])
            ->with('success', __('treasury.flash.payment_member_removed'));
    }
}
