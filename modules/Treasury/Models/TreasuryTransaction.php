<?php

declare(strict_types=1);

namespace Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Members\Models\Member;
use Modules\Treasury\Database\Factories\TreasuryTransactionFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a single financial transaction (income or expense) against a treasury account.
 *
 * The amount is always stored as a positive decimal; the type column determines
 * whether the transaction adds to or subtracts from the account balance.
 *
 * Optional links to member, event, or management task provide traceability
 * without duplicating data from those modules.
 *
 * Cross-module relations:
 *   event()  – requires Events module (optional, not in requires[]).
 *              Only call this relation when Schema::hasTable('events') is true.
 *              The FormRequest guards prevent event_id from being stored when
 *              the Events module is not installed.
 *   task()   – requires Management module (declared in module.json requires[]).
 *              Safe to call unconditionally when Treasury is installed.
 *   member() – requires Members module (declared in module.json requires[]).
 */
class TreasuryTransaction extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'account_id',
        'category_id',
        'type',
        'amount',
        'description',
        'transaction_date',
        'reference_number',
        'member_id',
        'event_id',
        'task_id',
        'created_by',
    ];

    protected $casts = [
        'amount'           => 'decimal:2',
        'transaction_date' => 'date',
    ];

    protected static function newFactory(): TreasuryTransactionFactory
    {
        return TreasuryTransactionFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'account_id',
                'category_id',
                'type',
                'amount',
                'description',
                'transaction_date',
                'reference_number',
                'member_id',
                'event_id',
                'task_id',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('treasury');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the account this transaction is booked against.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'account_id');
    }

    /**
     * Returns the optional category classification.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TreasuryCategory::class, 'category_id');
    }

    /**
     * Returns the optional club member this transaction is linked to.
     *
     * Members module is declared in requires[] — safe to call unconditionally.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /**
     * Returns the optional event this transaction is linked to.
     *
     * Events module is an OPTIONAL soft-dependency (not in requires[]).
     * Guard with Schema::hasTable('events') or class_exists() at the call site.
     * The StoreTransactionRequest and UpdateTransactionRequest only validate
     * event_id when the events table exists, preventing orphaned IDs.
     *
     * @see StoreTransactionRequest::rules()
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(\Modules\Events\Models\Event::class, 'event_id');
    }

    /**
     * Returns the optional management task this transaction is linked to.
     *
     * Management module is declared in requires[] — safe to call unconditionally.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(\Modules\Management\Models\ManagementTask::class, 'task_id');
    }

    /**
     * Returns the user who recorded this transaction.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the signed amount: positive for income, negative for expense.
     */
    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->type === 'income' ? $amount : -$amount;
    }
}