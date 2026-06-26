<?php

declare(strict_types=1);

namespace Modules\Management\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Management\Database\Factories\ManagementTaskFactory;

class ManagementTask extends Model
{
    use HasFactory;

    protected $table = 'management_tasks';

    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    protected static function newFactory(): ManagementTaskFactory
    {
        return ManagementTaskFactory::new();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Teams\Models\Team::class,
            'management_task_team',
            'task_id',
            'team_id'
        )->withPivot('created_by')->withTimestamps();
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            \Modules\Members\Models\Member::class,
            'management_task_member',
            'task_id',
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
