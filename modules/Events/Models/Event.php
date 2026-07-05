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
 * The event_team pivot is owned by Events (module.json → tables[]).
 *
 * The teams() Eloquent relation uses a string-based class name so it does not
 * cause autoload failures if the Teams module is not installed.
 * It may only be called from contexts where Teams is active.
 *
 * Event-specific tasks (event_tasks, event_task_categories, event_task_members)
 * are owned by the Management module. All task AJAX operations and task-related
 * controllers live in Management. The event_tasks guard in show() uses
 * Schema::hasTable('event_tasks') to detect Management presence.
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
        // Per-event feature flags (added in migration 2026_07_05_000001).
        // All default to true so existing events are unaffected after the migration.
        'tasks_enabled',
        'functions_enabled',
        'slots_enabled',
    ];

    protected $casts = [
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
        // Feature flags: cast to bool so Blade @if($event->tasks_enabled) works naturally.
        'tasks_enabled'     => 'boolean',
        'functions_enabled' => 'boolean',
        'slots_enabled'     => 'boolean',
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

}
