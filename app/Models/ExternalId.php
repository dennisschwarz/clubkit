<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExternalId extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'federation',
        'external_id',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Filtert nach einem bestimmten Verband.
     */
    public function scopeForFederation(\Illuminate\Database\Eloquent\Builder $query, string $federation): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('federation', $federation);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * n:1 – Eine externe ID gehört zu einem Member.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
