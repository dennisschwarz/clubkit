<?php

declare(strict_types=1);

namespace Modules\Treasury\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Treasury\Database\Factories\TreasuryAccountFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a financial account (e.g. Girokonto, Barkasse) within the club treasury.
 *
 * Accounts can be nested: a top-level account (parent_id = null) may have
 * sub-accounts. Each account manages its own set of transactions independently.
 *
 * The current balance is not persisted but computed on demand from transactions
 * to avoid synchronisation issues between stored balance and the transaction ledger.
 *
 * Visibility controls who may see this account:
 *   public          – all users with treasury.view
 *   team_restricted – only users linked to a member of an assigned team
 *                     (or with treasury.accounts.manage, which overrides the check)
 *
 * Cross-module relations:
 *   teams() – requires Teams module (optional soft-dependency, not in requires[]).
 *             The treasury_account_team pivot stores team_id without a DB-level FK
 *             so that deinstalling Teams does not break existing accounts.
 *             Guard with class_exists(\Modules\Teams\Models\Team::class) at the call site.
 *             TreasuryVisibilityService already applies this guard correctly.
 */
class TreasuryAccount extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'visibility',
        'created_by',
    ];

    protected $casts = [
        'parent_id' => 'integer',
    ];

    protected static function newFactory(): TreasuryAccountFactory
    {
        return TreasuryAccountFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'parent_id', 'visibility'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('treasury');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the parent account when this is a sub-account.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Returns all direct sub-accounts of this account.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Returns all transactions booked directly against this account.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(TreasuryTransaction::class, 'account_id');
    }

    /**
     * Returns the treasury_task_meta records that link tasks to this account.
     */
    public function taskMetas(): HasMany
    {
        return $this->hasMany(TreasuryTaskMeta::class, 'account_id');
    }

    /**
     * Returns the teams that may access this account when visibility = team_restricted.
     *
     * Teams module is an OPTIONAL soft-dependency (not in requires[]).
     * The treasury_account_team pivot stores team_id without a DB-level FK constraint,
     * so Teams can be deinstalled without breaking existing account records.
     *
     * Guard at the call site:
     *   if (class_exists(\Modules\Teams\Models\Team::class)) { $account->teams()->sync(...); }
     *
     * TreasuryVisibilityService::memberIsInTeams() already applies this guard.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'treasury_account_team',
            'treasury_account_id',
            'team_id'
        );
    }

    /**
     * Returns the user who created this account.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Computed helpers ──────────────────────────────────────────────────────

    /**
     * Returns the current balance computed from direct transactions only.
     *
     * Income is added, expenses are subtracted.
     * Sub-accounts are not included in this figure; see consolidatedBalance().
     * Avoid calling in loops — performs two aggregate queries per call.
     * Use TreasuryController's aggregated query pattern for bulk stats.
     */
    public function computedBalance(): float
    {
        $income  = (float) $this->transactions()->where('type', 'income')->sum('amount');
        $expense = (float) $this->transactions()->where('type', 'expense')->sum('amount');

        return $income - $expense;
    }

    /**
     * Returns the consolidated balance including all sub-accounts recursively.
     *
     * Warning: this is an N+1 query per level of nesting. Use only for display,
     * never inside loops over large account sets.
     */
    public function consolidatedBalance(): float
    {
        $balance = $this->computedBalance();

        foreach ($this->children as $child) {
            $balance += $child->consolidatedBalance();
        }

        return $balance;
    }

    /**
     * Returns the minimal JS representation used in modal dropdowns.
     *
     * @return array{id: int, name: string, description: string|null, visibility: string, parent_id: int|null}
     */
    public function toJsOption(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'visibility'  => $this->visibility,
            'parent_id'   => $this->parent_id,
        ];
    }
}
