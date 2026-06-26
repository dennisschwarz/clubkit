<?php

declare(strict_types=1);

namespace Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Import\Database\Factories\ImportSessionFactory;

class ImportSession extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'created_by',
        'source',
        'filename',
        'column_headers',
        'raw_rows',
        'samples',
        'mapping',
        'processed_rows',
        'expires_at',
    ];

    protected $casts = [
        'column_headers' => 'array',
        'raw_rows'       => 'array',
        'samples'        => 'array',
        'mapping'        => 'array',
        'processed_rows' => 'array',
        'expires_at'     => 'datetime',
    ];

    protected static function newFactory(): ImportSessionFactory
    {
        return ImportSessionFactory::new();
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ImportSession $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
