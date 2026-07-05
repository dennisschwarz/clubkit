<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Management\Database\Factories\EventTaskMemberFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a member assignment to a specific event task.
 *
 * Moved from the Events module (was EventTaskMember on event_task_member) to
 * the Management module where all task-related tables and controllers now live.
 *
 * Uses event_task_id (direct FK to event_tasks.id) instead of the old composite
 * (event_id, task_id) key. This makes queries and relations significantly simpler.
 *
 * time_from / time_to semantics:
 *   NULL / NULL  → assigned via the tasks tab (no shift window, member is listed
 *                   under the task in the tasks tab)
 *   SET  / SET   → assigned via the Einsatzplan tab with a specific time window
 *                   (the assignment also appears in the staffing grid)
 *
 * member_id has no DB-level FK (REGEL 13):
 *   Management's requires[] does not include Members. Referential integrity is
 *   enforced at the application layer (EventTaskMemberController validates member_id).
 *   In practice Members is always installed (Events requires Members).
 *
 * @property int                             $id
 * @property int                             $event_task_id
 * @property int                             $member_id
 * @property \Illuminate\Support\Carbon|null $time_from
 * @property \Illuminate\Support\Carbon|null $time_to
 */
class EventTaskMember extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'event_task_members';

    protected $fillable = [
        'event_task_id',
        'member_id',
        'time_from',
        'time_to',
    ];

    protected $casts = [
        'time_from' => 'datetime',
        'time_to'   => 'datetime',
    ];

    /**
     * @return EventTaskMemberFactory
     */
    protected static function newFactory(): EventTaskMemberFactory
    {
        return EventTaskMemberFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['event_task_id', 'member_id', 'time_from', 'time_to'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the event task this assignment belongs to.
     *
     * @return BelongsTo
     */
    public function eventTask(): BelongsTo
    {
        return $this->belongsTo(EventTask::class, 'event_task_id');
    }

    /**
     * Returns the assigned member.
     *
     * Direct import: Members module is always installed in practice
     * (Events requires Members, Management extends Events functionality).
     *
     * @return BelongsTo
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(\Modules\Members\Models\Member::class, 'member_id');
    }
}
