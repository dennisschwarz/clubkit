<?php

declare(strict_types=1);

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Events\Database\Factories\EventTaskMemberFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Assigns a member to an event task, with an optional time window.
 *
 * Two use cases (distinguished by time_from / time_to):
 *
 * 1. Aufgaben-Tab — responsible person, no specific time:
 *    time_from = null, time_to = null
 *
 * 2. Einsatzplan-Tab — shift with a time window:
 *    time_from = full datetime (event date + shift start)
 *    time_to   = full datetime (event date + shift end)
 *
 * CONSTRAINT: time_from and time_to must both be null or both be set.
 * Enforced in the saving boot hook.
 *
 * UNIQUE: (event_id, task_id, member_id) — one assignment per member per task per event.
 *
 * ── Architecture note ─────────────────────────────────────────────────────────
 *
 * This model intentionally has NO task() relation to ManagementTask.
 * Management is an optional module (not in Events' requires[]).
 * Any view that needs to display task data (name, category) is part of
 * the Management module and queries management_tasks directly via DB::table().
 *
 * The task_id foreign key value is always accessible as $this->task_id (int).
 *
 * @property int                             $id
 * @property int                             $event_id
 * @property int                             $task_id
 * @property int                             $member_id
 * @property \Illuminate\Support\Carbon|null $time_from
 * @property \Illuminate\Support\Carbon|null $time_to
 */
class EventTaskMember extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'event_task_member';

    protected $fillable = [
        'event_id',
        'task_id',
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
            ->logOnly(['event_id', 'task_id', 'member_id', 'time_from', 'time_to'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('events');
    }

    // ── Boot ──────────────────────────────────────────────────────────────────

    /**
     * Enforce that time_from and time_to are both null or both set.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $hasFrom = $model->time_from !== null;
            $hasTo   = $model->time_to   !== null;

            if ($hasFrom !== $hasTo) {
                throw new \LogicException(
                    'EventTaskMember: time_from and time_to must both be null or both be set.'
                );
            }
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Whether this entry represents a shift with a time window (Einsatzplan).
     *
     * @return bool
     */
    public function hasTimeSlot(): bool
    {
        return $this->time_from !== null;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the event this assignment belongs to.
     *
     * @return BelongsTo<Event, self>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Returns the assigned member.
     *
     * @return BelongsTo<\Modules\Members\Models\Member, self>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(\Modules\Members\Models\Member::class);
    }
}