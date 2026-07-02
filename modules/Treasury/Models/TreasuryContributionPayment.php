<?php

declare(strict_types=1);

namespace Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Members\Models\Member;
use Modules\Management\Models\ManagementTask;
use Modules\Treasury\Database\Factories\TreasuryContributionPaymentFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tracks per-member payment obligations for a contribution task.
 *
 * A row exists for each member who is expected to pay for a given task.
 *   paid_at = null  → payment outstanding
 *   paid_at = <ts>  → payment received on that date
 *
 * When the Kassenwart books the payment, a TreasuryTransaction is created and
 * its ID is stored in transaction_id for traceability.
 *
 * member_id is nullable: if a member is deleted from the system, their payment
 * history is preserved with member_id = null to maintain the financial audit trail.
 *
 * Unique constraint: (task_id, member_id) — one entry per member per task.
 */
class TreasuryContributionPayment extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'task_id',
        'member_id',
        'amount',
        'paid_at',
        'transaction_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function newFactory(): TreasuryContributionPaymentFactory
    {
        return TreasuryContributionPaymentFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['task_id', 'member_id', 'amount', 'paid_at', 'transaction_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('treasury');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the management task this payment belongs to.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'task_id');
    }

    /**
     * Returns the club member who is expected to pay.
     *
     * Returns null when the member has been deleted from the system.
     * member_id is nullable to preserve financial history after member deletion.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Returns the treasury transaction created when this payment was booked.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(TreasuryTransaction::class, 'transaction_id');
    }

    /**
     * Returns the user who created this payment entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns whether this payment has been received.
     */
    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }
}
