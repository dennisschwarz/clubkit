<?php

declare(strict_types=1);

namespace Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Import\Database\Factories\MemberImportLogFactory;

/**
 * Audit record written after every completed member import.
 *
 * Stores the source, filename, and row counts (created/updated/skipped).
 * The created_by foreign key uses nullOnDelete so audit logs are never
 * cascade-deleted when a user account is removed.
 *
 * LogsActivity is intentionally excluded: MemberImportLog IS itself an audit record.
 * Adding spatie/activitylog on top of an audit log table would produce redundant
 * meta-audit entries with no operational value.
 */
class MemberImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'source',
        'filename',
        'created_count',
        'updated_count',
        'skipped_count',
        'created_by',
    ];

    protected $casts = [
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'skipped_count' => 'integer',
    ];

    /**
     * @return MemberImportLogFactory
     */
    protected static function newFactory(): MemberImportLogFactory
    {
        return MemberImportLogFactory::new();
    }

    /**
     * Returns the user who executed the import.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
