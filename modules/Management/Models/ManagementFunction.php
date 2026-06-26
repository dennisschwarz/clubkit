<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Management\Database\Factories\ManagementFunctionFactory;

class ManagementFunction extends Model
{
    use HasFactory;

    protected $table = 'management_functions';

    protected $fillable = [
        'name',
        'created_by',
    ];

    protected static function newFactory(): ManagementFunctionFactory
    {
        return ManagementFunctionFactory::new();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'management_function_team',
            'role_id',
            'team_id'
        )->withPivot('created_by')->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Members\Models\Member::class,
            'management_function_member',
            'role_id',
            'member_id'
        )->withPivot('created_by')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function scopeGeneral(Builder $query): Builder
    {
        return $query->whereDoesntHave('teams');
    }

    public function scopeForTeam(Builder $query, int $teamId): Builder
    {
        return $query->whereHas('teams', fn ($q) => $q->where('teams.id', $teamId));
    }
}
