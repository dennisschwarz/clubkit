<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Member extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'contact_id',
        'user_id',
        'is_player',
        'notes',
    ];

    protected $casts = [
        'is_player' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Nur Spieler/Sportler.
     */
    public function scopePlayers(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_player', true);
    }

    /**
     * Nur Members mit einem verknüpften Login.
     */
    public function scopeWithLogin(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('user_id');
    }

    /**
     * Nur Members ohne Login.
     */
    public function scopeWithoutLogin(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('user_id');
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Convenience-Zugriff auf den vollständigen Namen via Contact.
     */
    public function getFullNameAttribute(): string
    {
        return $this->contact?->full_name ?? '';
    }

    /**
     * Prüft ob dieser Member einen Login hat.
     */
    public function hasLogin(): bool
    {
        return $this->user_id !== null;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * 1:1 (n:1) – Ein Member hat genau einen Kontaktdatensatz.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * n:1 – Ein Member kann (optional) einen Login-Account haben.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * n:m – Ein Member kann in mehreren Teams sein (via member_team Pivot).
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'member_team')
                    ->withPivot(['joined_on', 'left_on'])
                    ->withTimestamps();
    }

    /**
     * 1:n – Externe Verbands-IDs (DFBnet, Handball, etc.).
     */
    public function externalIds(): HasMany
    {
        return $this->hasMany(ExternalId::class);
    }

    /**
     * Gibt die externe ID für einen bestimmten Verband zurück.
     */
    public function externalIdFor(string $federation): ?string
    {
        return $this->externalIds()
                    ->where('federation', $federation)
                    ->value('external_id');
    }
}
