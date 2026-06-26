<?php

declare(strict_types=1);

namespace Modules\Import\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Import\Database\Factories\MemberImportLogFactory;

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

    protected static function newFactory(): MemberImportLogFactory
    {
        return MemberImportLogFactory::new();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
