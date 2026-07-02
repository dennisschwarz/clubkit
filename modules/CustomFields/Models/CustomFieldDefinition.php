<?php

declare(strict_types=1);

namespace Modules\CustomFields\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CustomFields\Database\Factories\CustomFieldDefinitionFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Defines a single custom field that can be attached to any object type.
 *
 * A definition describes the structure (type, label, options, validation rules)
 * and is shared across all entity instances of the given object_type.
 * Actual values per entity are stored in CustomFieldValue.
 *
 * The slug is unique per object_type and is generated automatically
 * from the label in CustomFieldDefinitionController.
 */
class CustomFieldDefinition extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'custom_field_definitions';

    protected $fillable = [
        'object_type',
        'label',
        'slug',
        'field_type',
        'options',
        'placeholder',
        'is_required',
        'sort_order',
        'created_by',
    ];

    /**
     * Eloquent-level defaults for attributes that the DB migration defines with a default.
     *
     * In Laravel 13, boolean/integer casts applied to null (attribute not set after create()
     * without the key) return null instead of casting the DB default — because castAttribute()
     * short-circuits for primitive casts when the raw value is null.
     *
     * Declaring $attributes here ensures the model ALWAYS has a proper in-memory default
     * even when the key was omitted from the create() call.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_required' => false,
        'sort_order'  => 0,
    ];

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    /**
     * @return CustomFieldDefinitionFactory
     */
    protected static function newFactory(): CustomFieldDefinitionFactory
    {
        return CustomFieldDefinitionFactory::new();
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * created_by is excluded (internal column).
     * Structural changes to field definitions are important admin audit events.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'object_type',
                'label',
                'slug',
                'field_type',
                'options',
                'placeholder',
                'is_required',
                'sort_order',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('custom-fields');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns all stored values for this field definition across all entities.
     *
     * FK column: definition_id (renamed from field_id in migration M8).
     *
     * @return HasMany
     */
    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'definition_id');
    }

    /**
     * Returns the user who created this field definition.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns the options array as a newline-separated string for textarea rendering.
     * Returns an empty string when no options are set.
     *
     * @return string
     */
    public function optionsAsText(): string
    {
        if (empty($this->options)) {
            return '';
        }

        return implode("\n", $this->options);
    }

    /**
     * Returns whether this field type supports a predefined option list.
     * Only the 'select' field type renders a dropdown with options.
     *
     * @return bool
     */
    public function hasOptions(): bool
    {
        return $this->field_type === 'select';
    }
}
