<?php

declare(strict_types=1);

namespace Modules\Teams\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Members\Models\Member;
use Modules\Teams\Database\Factories\TeamFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Represents a sports team within the club.
 *
 * Teams contain members via the team_member pivot table.
 * The eligible_only flag restricts membership to players who have
 * a current playing eligibility (eligible_to_play_date set and not in the future).
 *
 * Activity logging tracks structural changes to the team record itself.
 * Pivot membership changes are logged manually in TeamController
 * because the team_member pivot is not a first-class Eloquent model.
 */
class Team extends Model
{
    use HasFactory, LogsActivity;

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

    /**
     * @return TeamFactory
     */
    protected static function newFactory(): TeamFactory
    {
        return TeamFactory::new();
    }

    /**
     * Clean up cross-module pivot rows when a team is deleted.
     *
     * management_function_team and management_task_team carry no DB-level FK to teams
     * (Management is an optional dependency of Teams). Cascade is therefore handled here
     * at the Eloquent layer, mirroring what the controller does during HTTP requests.
     *
     * Schema guards ensure this is a no-op when the Management module is not installed.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::deleting(function (self $team): void {
            if (Schema::hasTable('management_function_team')) {
                DB::table('management_function_team')->where('team_id', $team->id)->delete();
            }
            if (Schema::hasTable('management_task_team')) {
                DB::table('management_task_team')->where('team_id', $team->id)->delete();
            }
        });
    }

    // ── Activity Log ──────────────────────────────────────────────────────────

    /**
     * Configures activity log behaviour for this model.
     *
     * created_by is excluded (internal column, not a meaningful change).
     * Pivot membership changes are handled separately in TeamController.
     *
     * @return LogOptions
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name',
                'color',
                'is_competition',
                'eligible_only',
                'season',
                'league',
                'age_class',
                'is_active',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('teams');
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    /**
     * Returns the user who created this team record.
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Returns all members of this team via the team_member pivot.
     *
     * The pivot carries squad_number, joined_at, and timestamps.
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'team_member')
                    ->withPivot('squad_number', 'joined_at')
                    ->withTimestamps();
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Returns whether the given member may be added to this team.
     *
     * When eligible_only is true, only members with a current playing
     * eligibility (eligible_to_play_date set and not in the future) are allowed.
     *
     * @param  Member $member
     * @return bool
     */
    public function canAddMember(Member $member): bool
    {
        if ($this->eligible_only && ! $member->eligible_to_play) {
            return false;
        }

        return true;
    }
}
