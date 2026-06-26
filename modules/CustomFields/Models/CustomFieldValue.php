<?php

declare(strict_types=1);

namespace Modules\CustomFields\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\CustomFields\Database\Factories\CustomFieldValueFactory;

class CustomFieldValue extends Model
{
    use HasFactory;

    protected $table = 'custom_field_values';

    protected $fillable = [
        'field_id',
        'entity_id',
        'value',
    ];

    protected static function newFactory(): CustomFieldValueFactory
    {
        return CustomFieldValueFactory::new();
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomFieldDefinition::class, 'field_id');
    }
}
