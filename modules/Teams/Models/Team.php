<?php

declare(strict_types=1);

namespace Modules\Teams\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Members\Models\Member;
use Modules\Teams\Database\Factories\TeamFactory;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'is_competition',
        'eligible_only',
        'season',
        'league',
        'age_class',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_competition' => 'boolean',
        'eligible_only'  => 'boolean',
        'is_active'      => 'boolean',
    ];

    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_member')
                    ->withPivot('squad_number')
                    ->withTimestamps();
    }

    public function canAddMember(Member $member): bool
    {
        if ($this->eligible_only && !$member->eligible_to_play) {
            return false;
        }

        return true;
    }
}
