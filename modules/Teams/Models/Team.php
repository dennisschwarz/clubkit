<?php

namespace Modules\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Members\Models\Member;

class Team extends Model
{
    protected $fillable = [
        'name',
        'season',
        'league',
        'age_class',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Mitglieder dieses Teams (nur spielberechtigte können hinzugefügt werden).
     * Pivot enthält: squad_number
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_member')
                    ->withPivot('squad_number')
                    ->withTimestamps();
    }
}
