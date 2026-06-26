<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Database\Factories\MemberRelationFactory;

class MemberRelation extends Model
{
    use HasFactory;

    protected $table = 'member_relations';

    protected $fillable = [
        'primary_member_id',
        'secondary_member_id',
        'relationship',
        'created_by',
    ];

    protected static function newFactory(): MemberRelationFactory
    {
        return MemberRelationFactory::new();
    }

    public function primaryMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'primary_member_id');
    }

    public function secondaryMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'secondary_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('primary_member_id', $memberId)
                     ->orWhere('secondary_member_id', $memberId);
    }
}
