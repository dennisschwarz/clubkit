<?php

declare(strict_types=1);

namespace Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Management\Models\ManagementTask;
use Modules\Treasury\Database\Factories\TreasuryTaskMetaFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Extension record that adds treasury meaning to a management task.
 *
 * A management_task row with a corresponding treasury_task_meta row is treated
 * as a contribution task in the treasury (e.g. "Jahresbeitrag 2026").
 * The task itself remains a standard ManagementTask; this table adds the financial
 * context (which account, default amount, due date) without modifying the task model.
 *
 * Relationship cardinality: one task → at most one treasury_task_meta row.
 */
class TreasuryTaskMeta extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'treasury_task_meta';

    protected $fillable = [
        'task_id',
        'account_id',
        'default_amount',
        'due_date',
        'created_by',
    ];

    protected $casts = [
        'default_amount' => 'decimal:2',
        'due_date'       => 'date',
    ];

    protected static function newFactory(): TreasuryTaskMetaFactory
    {
        return TreasuryTaskMetaFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['task_id', 'account_id', 'default_amount', 'due_date'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('treasury');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the management task this meta record extends.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'task_id');
    }

    /**
     * Returns the treasury account that receives payments for this task.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(TreasuryAccount::class, 'account_id');
    }

    /**
     * Returns all individual member payment entries for this task.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TreasuryContributionPayment::class, 'task_id', 'task_id');
    }

    /**
     * Returns the user who linked this task to the treasury.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
