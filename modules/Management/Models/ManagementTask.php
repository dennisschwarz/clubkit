<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Management\Database\Factories\ManagementTaskFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a club management task (e.g. drink sales, ticket checking).
 *
 * A task is a reusable template. It can be assigned to multiple events via
 * the event_task pivot, where per-event metadata (notes, completed, deadline_at)
 * lives on the pivot itself.
 *
 * Per-event member assignments (who does the task at a specific event, with an
 * optional time window) are stored in event_task_member.
 *
 * Tasks without any team or event assignment are considered general (club-wide).
 *
 * Priority values: 'normal' | 'important' | 'critical'
 * Validated by StoreTaskRequest / UpdateTaskRequest.
 *
 * Team integration uses string-based class names in the relation definitions
 * so they resolve at call-time rather than compile-time.
 * The general() and forTeam() scopes may only be called when Teams is active.
 */
class ManagementTask extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'management_tasks';

    /** @var list<string> Allowed priority values. */
    public const PRIORITIES = ['normal', 'important', 'critical'];

    protected $fillable = [
        'name',
        'description',
        'category_id',
        'priority',
        'created_by',
    ];

    /**
     * @return ManagementTaskFactory
     */
    protected static function newFactory(): ManagementTaskFactory
    {
        return ManagementTaskFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * created_by is excluded (internal column).
     *
     * Note: logOnlyDirty() is intentionally omitted. In Spatie ActivityLog v5 the
     * 'created' observer fires after Eloquent calls syncOriginal(), so getDirty()
     * is empty at that point. Logging all logOnly() fields on every persist is correct
     * behaviour; dontLogEmptyChanges() still prevents no-op saves from cluttering the log.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'category_id', 'priority'])
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the optional category this task belongs to.
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ManagementTaskCategory::class, 'category_id');
    }

    /**
     * Returns the teams this task is scoped to.
     *
     * This relation is called exclusively from Teams module hook views,
     * which are only registered when Teams is installed and its tables exist.
     * Management's own code never calls this relation directly.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'management_task_team',
            'task_id',
            'team_id'
        )->withPivot('created_by')->withTimestamps();
    }

    /**
     * Returns the members assigned to this task club-wide (default assignments).
     *
     * For event-specific member assignments, see event_task_member table.
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Members\Models\Member::class,
            'management_task_member',
            'task_id',
            'member_id'
        )->withPivot('created_by')->withTimestamps();
    }

    /**
     * Returns the user who created this task.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restricts the query to tasks not assigned to any team (club-wide).
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
     * Restricts the query to tasks assigned to the given team.
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

    /**
     * Restricts the query to tasks with the given priority.
     *
     * @param  Builder $query
     * @param  string  $priority  One of ManagementTask::PRIORITIES
     * @return Builder
     */
    public function scopeWithPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }
}
