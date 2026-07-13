<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Management\Database\Factories\EventFunctionFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents an event-scoped ad-hoc function (Option C).
 *
 * Unlike ManagementFunction (club-wide, reusable), an EventFunction is created
 * directly for a single event and is cascade-deleted when the event is removed.
 *
 * @property int         $id
 * @property int         $event_id
 * @property string      $name
 * @property int|null    $member_id
 * @property int|null    $created_by
 */
class EventFunction extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'event_functions';

    protected $fillable = [
        'event_id',
        'name',
        'member_id',
        'created_by',
    ];

    /**
     * @return EventFunctionFactory
     */
    protected static function newFactory(): EventFunctionFactory
    {
        return EventFunctionFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * event_id and created_by are internal columns excluded from logging.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'member_id'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('management');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the user who created this ad-hoc function.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
