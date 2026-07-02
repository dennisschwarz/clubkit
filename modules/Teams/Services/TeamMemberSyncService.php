<?php

declare(strict_types=1);

namespace Modules\Teams\Services;

use Illuminate\Support\Facades\DB;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

/**
 * Handles all team ↔ member roster synchronisation.
 *
 * Reused by TeamController::syncRoster() (Dual Listbox modal) and
 * TeamController::syncMemberTeams() (Member modal tab) – single source of truth.
 *
 * Eligibility rules are enforced for add operations only.
 * Remove operations are always allowed (administrative correction).
 */
class TeamMemberSyncService
{
    /**
     * Sync a team's entire roster from the team perspective.
     *
     * Members in $memberIds not yet in the team → attached (with joined_at).
     * Members currently in the team but absent from $memberIds → detached.
     * When $team->eligible_only is true, ineligible IDs are silently dropped before attaching.
     *
     * @param  Team    $team
     * @param  int[]   $memberIds
     * @return array{attached: int, detached: int}
     */
    public function syncRoster(Team $team, array $memberIds): array
    {
        $currentIds = $team->members()->pluck('members.id')->toArray();

        if ($team->eligible_only) {
            $memberIds = Member::whereIn('id', $memberIds)
                ->whereNotNull('eligible_to_play_date')
                ->whereDate('eligible_to_play_date', '<=', now())
                ->pluck('id')
                ->toArray();
        }

        $toDetach = array_values(array_diff($currentIds, $memberIds));
        $toAttach = array_values(array_diff($memberIds, $currentIds));

        foreach ($toDetach as $id) {
            $team->members()->detach($id);

            // Manual activity log: team_member pivot does not use LogsActivity trait.
            activity()->useLog('teams')
                ->on($team)
                ->withProperties(['member_id' => $id])
                ->log('member_removed');
        }

        foreach ($toAttach as $id) {
            $member = Member::find($id);
            if (! $member) {
                continue;
            }

            $team->members()->attach($id, ['joined_at' => now()]);

            activity()->useLog('teams')
                ->on($team)
                ->withProperties(['member_id' => $id, 'member_name' => $member->full_name])
                ->log('member_added');
        }

        return ['attached' => count($toAttach), 'detached' => count($toDetach)];
    }

    /**
     * Sync a member's team assignments across all teams.
     *
     * Teams in $teamIds the member is not yet in → attached.
     * Teams the member is in but absent from $teamIds → detached.
     * Inactive teams and eligibility violations are skipped silently for add operations only.
     *
     * @param  Member  $member
     * @param  int[]   $teamIds
     * @return array{added: int, removed: int}
     */
    public function syncMemberTeams(Member $member, array $teamIds): array
    {
        $currentIds = DB::table('team_member')
            ->where('member_id', $member->id)
            ->pluck('team_id')
            ->toArray();

        $toAdd    = array_values(array_diff($teamIds, $currentIds));
        $toRemove = array_values(array_diff($currentIds, $teamIds));

        $added   = 0;
        $removed = 0;

        foreach ($toAdd as $teamId) {
            $team = Team::find($teamId);
            if (! $team || ! $team->is_active) {
                continue;
            }
            if ($team->eligible_only && ! $member->eligible_to_play) {
                continue;
            }

            DB::table('team_member')->insertOrIgnore([
                'team_id'   => $teamId,
                'member_id' => $member->id,
                'joined_at' => now(),
            ]);

            activity()->useLog('teams')
                ->on($team)
                ->withProperties(['member_id' => $member->id, 'member_name' => $member->full_name])
                ->log('member_added');

            $added++;
        }

        foreach ($toRemove as $teamId) {
            DB::table('team_member')
                ->where('team_id', $teamId)
                ->where('member_id', $member->id)
                ->delete();

            $team = Team::find($teamId);
            if ($team) {
                activity()->useLog('teams')
                    ->on($team)
                    ->withProperties(['member_id' => $member->id, 'member_name' => $member->full_name])
                    ->log('member_removed');
            }

            $removed++;
        }

        return ['added' => $added, 'removed' => $removed];
    }
}
