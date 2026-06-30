<?php

declare(strict_types=1);

namespace Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Modules\Import\Database\Factories\ImportSessionFactory;

/**
 * Represents a temporary import session created during the CSV import wizard.
 *
 * Sessions are identified by a UUID primary key and expire after two hours.
 * Column headers, raw rows, mapping, and processed rows are stored as JSON.
 * Expired or completed sessions are deleted after the import executes.
 *
 * LogsActivity is intentionally excluded: ImportSession is transient wizard state that
 * expires after two hours and is deleted immediately on completion. Recording activity
 * on ephemeral session data would generate meaningless audit entries with no operational value.
 */
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

    /**
     * @return ImportSessionFactory
     */
    protected static function newFactory(): ImportSessionFactory
    {
        return ImportSessionFactory::new();
    }

    /**
     * Assigns a UUID as the primary key when creating a new session.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (ImportSession $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Returns the user who initiated the import session.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Returns true when the session has passed its expiry timestamp.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
