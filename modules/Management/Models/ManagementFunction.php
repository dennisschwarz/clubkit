<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Management\Database\Factories\ManagementFunctionFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a club management function (e.g. Trainer, Co-Trainer, Betreuer).
 *
 * Functions can be scoped to one or more teams via the management_function_team
 * pivot. Functions without any team assignment are considered general (club-wide).
 * Members can be assigned to a function via management_function_member.
 *
 * Note: pivot FK column is named role_id (legacy name, not renamed to function_id
 * to avoid breaking the existing DB schema).
 *
 * Team integration is provided by the Teams module via hook views. The teams()
 * relation may be called from Teams hook views when Teams is active. The Management
 * module itself never calls this relation directly — it only owns the pivot table.
 */
class ManagementFunction extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'management_functions';

    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    /**
     * @return ManagementFunctionFactory
     */
    protected static function newFactory(): ManagementFunctionFactory
    {
        return ManagementFunctionFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * created_by is excluded (internal column).
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the teams this function is scoped to.
     *
     * This relation is called exclusively from Teams module hook views,
     * which are only registered when Teams is installed and its tables exist.
     * Management's own code never calls this relation directly.
     *
     * Note: pivot FK is named role_id (legacy schema name).
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'management_function_team',
            'role_id',
            'team_id'
        )->withPivot('created_by')->withTimestamps();
    }

    /**
     * Returns the members assigned to this function.
     *
     * Note: pivot FK is named role_id (legacy schema name).
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Members\Models\Member::class,
            'management_function_member',
            'role_id',
            'member_id'
        )->withPivot('created_by')->withTimestamps();
    }

    /**
     * Returns the user who created this function.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restricts the query to functions that are not assigned to any team (club-wide).
     *
     * Only call this scope from Teams hook views where the teams table is guaranteed
     * to exist.
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereDoesntHave('teams');
    }

    /**
     * Restricts the query to functions assigned to the given team.
     *
     * Only call this scope from Teams hook views where the teams table is guaranteed
     * to exist.
     *
     * @param  Builder $query
     * @param  int     $teamId
     * @return Builder
     */
    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
    }
}
