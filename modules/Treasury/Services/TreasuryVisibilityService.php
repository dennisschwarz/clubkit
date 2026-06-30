<?php

declare(strict_types=1);

namespace Modules\Treasury\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Members\Models\Member;
use Modules\Treasury\Models\TreasuryAccount;

/**
 * Determines which treasury accounts a given user is allowed to see.
 *
 * Visibility rules (in priority order):
 *
 * 1. Users with 'treasury.accounts.manage' always see all accounts.
 * 2. Users without 'treasury.view' see nothing.
 * 3. Accounts with visibility='public': visible to all users with treasury.view.
 * 4. Accounts with visibility='team_restricted': visible only if the user is linked
 *    to a club member (members.user_id) who is either:
 *    (a) a direct member of one of the account's assigned teams, or
 *    (b) assigned to a management function that is scoped to one of those teams.
 *    Family-relation visibility (YouthClubMode) is planned for Phase 2.
 */
class TreasuryVisibilityService
{
    /**
     * Returns all accounts visible to the given user, eager-loading teams.
     *
     * @return Collection<int, TreasuryAccount>
     */
    public function visibleAccounts(User $user): Collection
    {
        // Account managers always see everything
        if ($user->can('treasury.accounts.manage')) {
            return TreasuryAccount::with('teams')->get();
        }

        if (! $user->can('treasury.view')) {
            return collect();
        }

        return TreasuryAccount::with('teams')
            ->get()
            ->filter(fn (TreasuryAccount $account) => $this->userCanSeeAccount($user, $account))
            ->values();
    }

    /**
     * Returns the IDs of all accounts visible to the given user.
     *
     * Prefer this over visibleAccounts() when you only need IDs for query scoping.
     *
     * @return list<int>
     */
    public function visibleAccountIds(User $user): array
    {
        return $this->visibleAccounts($user)->pluck('id')->all();
    }

    /**
     * Returns whether the given user can see the given account.
     *
     * Loads the account's teams relation when not already loaded.
     */
    public function userCanSeeAccount(User $user, TreasuryAccount $account): bool
    {
        if ($user->can('treasury.accounts.manage')) {
            return true;
        }

        if (! $user->can('treasury.view')) {
            return false;
        }

        if ($account->visibility === 'public') {
            return true;
        }

        // team_restricted: the user must be linked to a member profile
        $member = Member::where('user_id', $user->id)->first();

        if (! $member) {
            return false;
        }

        $teamIds = $account->teams->pluck('id');

        if ($teamIds->isEmpty()) {
            // Team-restricted but no teams assigned → nobody (except managers) can see it
            return false;
        }

        // Check (a): member is directly in one of the account's teams
        if ($this->memberIsInTeams($member, $teamIds->all())) {
            return true;
        }

        // Check (b): member has a management function scoped to one of those teams
        if ($this->memberHasFunctionInTeams($member, $teamIds->all())) {
            return true;
        }

        return false;
    }

    /**
     * Returns whether the member is in any of the given teams via the team_member pivot.
     *
     * @param list<int> $teamIds
     */
    private function memberIsInTeams(Member $member, array $teamIds): bool
    {
        if (! class_exists(\Modules\Teams\Models\Team::class)) {
            return false;
        }

        return \Modules\Teams\Models\Team::whereIn('id', $teamIds)
            ->whereHas('members', fn ($q) => $q->where('members.id', $member->id))
            ->exists();
    }

    /**
     * Returns whether the member holds a management function assigned to any of the given teams.
     *
     * Uses management_function_member → management_function_team join.
     * Gracefully returns false when the Management module is not installed.
     *
     * @param list<int> $teamIds
     */
    private function memberHasFunctionInTeams(Member $member, array $teamIds): bool
    {
        if (! class_exists(\Modules\Management\Models\ManagementFunction::class)) {
            return false;
        }

        return \Modules\Management\Models\ManagementFunction::whereHas(
            'teams',
            fn ($q) => $q->whereIn('teams.id', $teamIds)
        )
            ->whereHas(
                'members',
                fn ($q) => $q->where('members.id', $member->id)
            )
            ->exists();
    }
}
