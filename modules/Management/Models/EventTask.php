<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Management\Database\Factories\EventTaskFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a task created for a specific event.
 *
 * An event task is either:
 *   - Imported from the global library: template_id references management_tasks.id
 *   - Created directly on the event:    template_id is NULL
 *
 * In both cases name and priority are stored locally on this record.
 * The event task is therefore fully self-contained: deleting the global template
 * sets template_id to NULL but preserves all data (name was copied at import time).
 *
 * Category (event_task_categories):
 *   NULL → task appears in the "Allgemein" section of the tasks tab.
 *   Tasks can be moved between categories via drag & drop (updates category_id + sort_order).
 *
 * deadline_at semantics:
 *   NULL  → event-day task (visible on the tasks tab by default)
 *   SET   → preparation task with a concrete deadline before the event
 *
 * Priority values: 'normal' | 'important' | 'critical'
 *   Validated by StoreEventTaskRequest.
 *
 * @property int                             $id
 * @property int                             $event_id
 * @property int|null                        $category_id
 * @property int|null                        $template_id
 * @property string                          $name
 * @property string                          $priority
 * @property int                             $sort_order
 * @property \Illuminate\Support\Carbon|null $deadline_at
 * @property bool                            $completed
 * @property string|null                     $notes
 * @property int|null                        $created_by
 */
class EventTask extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'event_tasks';

    /** @var list<string> Allowed priority values. */
    public const PRIORITIES = ['normal', 'important', 'critical'];

    protected $fillable = [
        'event_id',
        'category_id',
        'template_id',
        'name',
        'priority',
        'sort_order',
        'deadline_at',
        'completed',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'completed'   => 'boolean',
        'deadline_at' => 'datetime',
        'sort_order'  => 'integer',
    ];

    /**
     * @return EventTaskFactory
     */
    protected static function newFactory(): EventTaskFactory
    {
        return EventTaskFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * template_id and created_by are excluded (internal / FK columns).
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'priority', 'category_id', 'sort_order', 'deadline_at', 'completed', 'notes'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the event this task belongs to.
     *
     * Uses a string class name: Events module is not listed in Management's requires[].
     * Only call this relation when Events is confirmed to be installed.
     *
     * @return BelongsTo
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo('Modules\\Events\\Models\\Event', 'event_id');
    }

    /**
     * Returns the event-local category this task is grouped under.
     *
     * NULL when the task has no category (shown in the "Allgemein" section).
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(EventTaskCategory::class, 'category_id');
    }

    /**
     * Returns the global task template this task was imported from.
     *
     * NULL when the task was created directly on the event (not from the library).
     * The template may have been deleted — always guard with a null-check.
     *
     * @return BelongsTo
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ManagementTask::class, 'template_id');
    }

    /**
     * Returns all member assignments for this event task.
     *
     * @return HasMany
     */
    public function members(): HasMany
    {
        return $this->hasMany(EventTaskMember::class, 'event_task_id');
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
}
