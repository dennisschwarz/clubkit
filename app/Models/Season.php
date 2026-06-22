<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Season extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'starts_on',
        'ends_on',
        'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on'   => 'date',
        'is_active' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Gibt nur die aktuell aktive Saison zurück.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * 1:n – Eine Saison hat mehrere Teams.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class)->orderBy('sort_order');
    }
}
