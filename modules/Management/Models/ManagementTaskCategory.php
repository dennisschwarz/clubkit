<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Management\Database\Factories\ManagementTaskCategoryFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents an optional grouping category for management tasks.
 *
 * A task may belong to at most one category (nullable FK on management_tasks).
 * When a category is deleted, all linked tasks retain their data but
 * their category_id is set to NULL via the DB ON DELETE SET NULL constraint.
 *
 * Categories are managed in the Management module settings section.
 */
class ManagementTaskCategory extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'management_task_categories';

    protected $fillable = [
        'name',
        'color',
        'created_by',
    ];

    /**
     * @return ManagementTaskCategoryFactory
     */
    protected static function newFactory(): ManagementTaskCategoryFactory
    {
        return ManagementTaskCategoryFactory::new();
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
     * Returns all tasks that belong to this category.
     *
     * When the category is deleted, tasks are NOT deleted;
     * their category_id is set to NULL by the DB constraint.
     *
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ManagementTask::class, 'category_id');
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
