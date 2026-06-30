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

/**
 * Represents a family relationship between two club members.
 *
 * Storage convention (canonical form):
 *   primary_member_id   = parent (or lower-ID sibling)
 *   secondary_member_id = child  (or higher-ID sibling)
 *   relationship        = 'father' | 'mother' | 'sibling'
 *
 * Direction normalisation is handled by FamilyController::resolveDirection()
 * before saving, so every record always follows the canonical form.
 *
 * No LogsActivity trait: this model is a thin join record.
 * Create/delete events are logged manually in FamilyController.
 */
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

    /**
     * @return MemberRelationFactory
     */
    protected static function newFactory(): MemberRelationFactory
    {
        return MemberRelationFactory::new();
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the parent (or canonical left-side sibling) member.
     *
     * @return BelongsTo
     */
    public function primaryMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'primary_member_id');
    }

    /**
     * Returns the child (or canonical right-side sibling) member.
     *
     * @return BelongsTo
     */
    public function secondaryMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'secondary_member_id');
    }

    /**
     * Returns the user who created this relation record.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Restricts the query to relations where the given member
     * appears on either side (primary or secondary).
     *
     * @param  Builder $query
     * @param  int     $memberId
     * @return Builder
     */
    public function scopeForMember(Builder $query, int $memberId): Builder
    {
        return $query->where('primary_member_id', $memberId)
                     ->orWhere('secondary_member_id', $memberId);
    }
}
