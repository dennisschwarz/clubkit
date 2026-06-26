<?php

declare(strict_types=1);

namespace Modules\CustomFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\CustomFields\Database\Factories\CustomFieldDefinitionFactory;

class CustomFieldDefinition extends Model
{
    use HasFactory;

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

    protected $casts = [
        'options'     => 'array',
        'is_required' => 'boolean',
        'sort_order'  => 'integer',
    ];

    protected static function newFactory(): CustomFieldDefinitionFactory
    {
        return CustomFieldDefinitionFactory::new();
    }

    public function values(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class, 'field_id');
    }

    public function optionsAsText(): string
    {
        if (empty($this->options)) {
            return '';
        }

        return implode("\n", $this->options);
    }

    public function hasOptions(): bool
    {
        return $this->field_type === 'select';
    }
}
