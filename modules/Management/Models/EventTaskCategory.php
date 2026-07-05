<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Management\Database\Factories\EventTaskCategoryFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a task category scoped to a single event.
 *
 * Event task categories only exist within their parent event context.
 * They are rendered as collapsible, colour-coded sections on the event
 * detail tasks tab. Section headers match the ck-section-header pattern.
 *
 * When a category is deleted the DB SET NULL constraint on
 * event_tasks.category_id moves all its tasks to the "Allgemein" section
 * (category_id = NULL). No tasks are deleted.
 *
 * Colour slugs: blue | green | amber | red | orange | purple | pink | teal | navy | slate | gray
 *   Same system as Teams section colours and management_task_categories.
 *
 * Sort order:
 *   User-defined display position within the event tasks tab.
 *   Updated via the drag & drop reorder endpoint (EventTaskCategoryController::reorder).
 */
class EventTaskCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'event_task_categories';

    /** @var list<string> Allowed colour slug values (shared with Teams and ManagementTaskCategory). */
    public const COLORS = [
        'blue', 'green', 'amber', 'red', 'orange',
        'purple', 'pink', 'teal', 'navy', 'slate', 'gray',
    ];

    protected $fillable = [
        'event_id',
        'name',
        'color',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return EventTaskCategoryFactory
     */
    protected static function newFactory(): EventTaskCategoryFactory
    {
        return EventTaskCategoryFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * event_id and created_by are excluded (internal / FK columns).
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'color', 'sort_order'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the event this category belongs to.
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
     * Returns all event tasks grouped under this category.
     *
     * When the category is deleted, tasks are NOT deleted — their category_id
     * is set to NULL (DB ON DELETE SET NULL on event_tasks.category_id).
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class, 'category_id');
    }

    /**
     * Returns the user who created this category.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
