<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Contact extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'street',
        'street_number',
        'postal_code',
        'city',
    ];

    // ── Computed ──────────────────────────────────────────────────────────────

    /**
     * Vollständiger Name als einzelner String.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Vollständige Adresse als einzelner String (wenn vorhanden).
     */
    public function getFullAddressAttribute(): string
    {
        if (!$this->street) return '';
        return "{$this->street} {$this->street_number}, {$this->postal_code} {$this->city}";
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * 1:1 – Ein Kontakt gehört zu maximal einem Systemmitglied.
     */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }
}
