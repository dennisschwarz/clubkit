<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Team extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'season_id',
        'name',
        'slug',
        'color',
        'type',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeRegular(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', 'regular');
    }

    public function scopeTrial(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', 'trial');
    }

    public function scopeVirtual(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('type', 'virtual');
    }

    public function scopeOrdered(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('sort_order');
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    /**
     * n:1 – Ein Team gehört zu einer Saison.
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * n:m – Ein Team hat mehrere Members (via member_team Pivot).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'member_team')
                    ->withPivot(['joined_on', 'left_on'])
                    ->withTimestamps();
    }

    /**
     * Nur aktive Members (ohne Austrittsdatum oder Austritt in der Zukunft).
     */
    public function activeMembers(): BelongsToMany
    {
        return $this->members()
                    ->wherePivot('left_on', null)
                    ->orWherePivot('left_on', '>', now());
    }
}
