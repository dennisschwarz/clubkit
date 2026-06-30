<?php

declare(strict_types=1);

namespace Modules\Events\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Modules\Events\Database\Factories\EventFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a club event (match, tournament, training, meeting, etc.).
 *
 * Events are the primary grouping unit for task assignments.
 *
 * ── Architecture note ─────────────────────────────────────────────────────────
 *
 * Events avoids hard PHP dependencies on Management and Teams at the controller
 * and service layer. Cross-module wiring runs through hook views:
 *
 *   ManagementServiceProvider → hooks into events.show.tasks-panel,
 *                               event.table.besetzung.row, events.show.page.scripts
 *   TeamsServiceProvider      → hooks into event.table.teams.*,
 *                               events.show.teams-panel
 *
 * The pivot tables event_task and event_team are owned by Events (module.json → tables[]).
 * AJAX operations (completeTask, addTask, removeTask) use DB::table() to avoid
 * importing optional module models in the controller layer.
 *
 * The tasks() and teams() Eloquent relations below use string-based class names so
 * they do not cause autoload failures if the Management or Teams module is not
 * installed. They may only be called when the respective module is active.
 *
 * Helper methods hasTaskAssigned() and hasEventDayTaskAssigned() are kept for
 * EventSlotController / EventTaskMemberController, which must NOT import
 * ManagementTask directly.
 */
class Event extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'events';

    protected $fillable = [
        'title',
        'description',
        'starts_at',
        'ends_at',
        'location',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    /**
     * @return EventFactory
     */
    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * Note: logOnlyDirty() is intentionally omitted.
     * In Spatie ActivityLog v5 the 'created' observer fires after Eloquent calls
     * syncOriginal(), leaving getDirty() empty. With logOnlyDirty(), created events
     * would produce a log entry with an empty attributes array, making audit traces
     * unreliable. Logging all logOnly() fields on every persisted event is correct
     * for event audit trails. dontLogEmptyChanges() still prevents trivial no-op
     * updates (e.g. saving a record without any actual change) from cluttering the log.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'description', 'starts_at', 'ends_at', 'location', 'notes'])
            ->dontLogEmptyChanges()
            ->useLogName('events');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the user who created this event.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Returns the management tasks assigned to this event via the event_task pivot.
     *
     * Uses a string class name so the class is resolved at call-time, not at
     * compile-time. Calling this relation when the Management module is not active
     * will fail at runtime — only call it from contexts where Management is installed.
     *
     * For controller-layer guards that must avoid importing ManagementTask, use
     * hasTaskAssigned() / hasEventDayTaskAssigned() instead.
     *
     * @return BelongsToMany
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(
            'Modules\\Management\\Models\\ManagementTask',
            'event_task',
            'event_id',
            'task_id'
        )->withPivot(['deadline_at', 'completed', 'notes'])->withTimestamps();
    }

    /**
     * Returns the teams assigned to this event via the event_team pivot.
     *
     * Uses a string class name (runtime resolution). Only call when Teams is active.
     *
     * @return BelongsToMany
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            'Modules\\Teams\\Models\\Team',
            'event_team',
            'event_id',
            'team_id'
        )->withTimestamps();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restricts the query to upcoming events (starts in the future).
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now());
    }

    /**
     * Restricts the query to past events (already started).
     *
     * @param  Builder $query
     * @return Builder
     */
    public function scopePast(Builder $query): Builder
    {
        return $query->where('starts_at', '<', now());
    }

    // ── Task membership helpers ───────────────────────────────────────────────
    //
    // These methods query the event_task table (owned by Events) via raw DB::table()
    // so they can be called safely from EventSlotController and EventTaskMemberController
    // without any PHP import of ManagementTask.

    /**
     * Returns whether the given task is currently assigned to this event.
     *
     * Used by EventTaskMemberController to guard assignment creation.
     *
     * @param  int  $taskId  The management_tasks.id to check.
     * @return bool
     */
    public function hasTaskAssigned(int $taskId): bool
    {
        return DB::table('event_task')
            ->where('event_id', $this->id)
            ->where('task_id', $taskId)
            ->exists();
    }

    /**
     * Returns whether the given task is assigned to this event AND qualifies
     * as an event-day task (no deadline, or deadline matches the event date).
     *
     * Used by EventSlotController to guard time-slot creation.
     *
     * @param  int    $taskId     The management_tasks.id to check.
     * @param  string $eventDate  The event start date in Y-m-d format.
     * @return bool
     */
    public function hasEventDayTaskAssigned(int $taskId, string $eventDate): bool
    {
        return DB::table('event_task')
            ->where('event_id', $this->id)
            ->where('task_id', $taskId)
            ->where(function ($q) use ($eventDate): void {
                $q->whereNull('deadline_at')
                  ->orWhereDate('deadline_at', '=', $eventDate);
            })
            ->exists();
    }
}
