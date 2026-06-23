<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Members\Models\Member;

/**
 * Guardian link between two Member records.
 *
 * @property int    $id
 * @property int    $member_id         The child (player)
 * @property int    $parent_member_id  The guardian (also a player/member entry)
 * @property string $relationship      'father' | 'mother'
 */
class MemberParent extends Model
{
    protected $table = 'member_parents';

    protected $fillable = [
        'member_id',
        'parent_member_id',
        'relationship',
    ];

    /** The child */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }

    /** The guardian (also a Member entry) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'parent_member_id');
    }
}
