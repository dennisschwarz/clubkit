<?php

declare(strict_types=1);

namespace Modules\CustomFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CustomFields\Database\Factories\CustomFieldValueFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Stores the concrete value of a custom field for a specific entity.
 *
 * Each record links a CustomFieldDefinition (what field) to an entity_id
 * (which object instance, e.g. member ID 7) and stores the value as a string.
 * Typed casting (number, boolean, date) is the responsibility of the consuming view/controller.
 *
 * Unique constraint: (definition_id, entity_id) – one value per field per entity.
 *
 * FK column: definition_id (renamed from field_id in migration M8).
 */
class CustomFieldValue extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'custom_field_values';

    protected $fillable = [
        'definition_id', // renamed from field_id in migration M8
        'entity_id',
        'value',
    ];

    /**
     * @return CustomFieldValueFactory
     */
    protected static function newFactory(): CustomFieldValueFactory
    {
        return CustomFieldValueFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * All three columns are meaningful: changing any of them represents
     * a real data change worth tracking.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'definition_id',
                'entity_id',
                'value',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('custom-fields');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the field definition this value belongs to.
     *
     * FK column: definition_id (renamed from field_id in migration M8).
     *
     * @return BelongsTo
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'definition_id');
    }
}
