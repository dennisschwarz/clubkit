<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Modules\Teams\Services\TeamMemberSyncService;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Create a fresh Team instance for service tests.
 *
 * Uses factory to avoid duplicating makeTeam() from TeamModelTest.
 *
 * @param  array<string, mixed> $attrs
 * @return Team
 */
function syncTestTeam(array $attrs = []): Team
{
    return Team::factory()->create(array_merge(['is_active' => true, 'eligible_only' => false], $attrs));
}

/**
 * Create a member who is eligible to play (eligible_to_play_date in the past).
 *
 * @return Member
 */
function syncEligibleMember(): Member
{
    return Member::factory()->create([
        'eligible_to_play_date' => now()->subYear()->toDateString(),
    ]);
}

/**
 * Create a member who is NOT eligible to play (eligible_to_play_date is null).
 *
 * @return Member
 */
function syncIneligibleMember(): Member
{
    return Member::factory()->notEligible()->create();
}

// ── syncRoster ────────────────────────────────────────────────────────────────

test('syncRoster attaches members not yet in the team', function () {
    $team    = syncTestTeam();
    $member1 = syncEligibleMember();
    $member2 = syncEligibleMember();

    $service = new TeamMemberSyncService();
    $result  = $service->syncRoster($team, [$member1->id, $member2->id]);

    expect($result['attached'])->toBe(2);
    expect($result['detached'])->toBe(0);
    expect($team->members()->count())->toBe(2);
});

test('syncRoster detaches members absent from the given list', function () {
    $team    = syncTestTeam();
    $member1 = syncEligibleMember();
    $member2 = syncEligibleMember();
    $team->members()->attach([$member1->id, $member2->id], ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    // Keep only member1 → member2 should be detached.
    $result  = $service->syncRoster($team, [$member1->id]);

    expect($result['attached'])->toBe(0);
    expect($result['detached'])->toBe(1);
    expect($team->members()->where('members.id', $member2->id)->count())->toBe(0);
    expect($team->members()->where('members.id', $member1->id)->count())->toBe(1);
});

test('syncRoster clears the entire roster when an empty list is passed', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();
    $team->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $result  = $service->syncRoster($team, []);

    expect($result['detached'])->toBe(1);
    expect($team->members()->count())->toBe(0);
});

test('syncRoster does not re-attach a member already on the team', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();
    $team->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $result  = $service->syncRoster($team, [$member->id]);

    // Member was already in the team → 0 attached, 0 detached.
    expect($result['attached'])->toBe(0);
    expect($result['detached'])->toBe(0);
    expect($team->members()->count())->toBe(1);
});

test('syncRoster drops ineligible members from the list when eligible_only is true', function () {
    $team       = syncTestTeam(['eligible_only' => true]);
    $eligible   = syncEligibleMember();
    $ineligible = syncIneligibleMember();

    $service = new TeamMemberSyncService();
    $result  = $service->syncRoster($team, [$eligible->id, $ineligible->id]);

    // Only the eligible member should be attached.
    expect($result['attached'])->toBe(1);
    expect($team->members()->where('members.id', $eligible->id)->count())->toBe(1);
    expect($team->members()->where('members.id', $ineligible->id)->count())->toBe(0);
});

test('syncRoster returns correct counts for a mixed add-and-remove operation', function () {
    $team    = syncTestTeam();
    $staying = syncEligibleMember();
    $leaving = syncEligibleMember();
    $joining = syncEligibleMember();

    $team->members()->attach([$staying->id, $leaving->id], ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $result  = $service->syncRoster($team, [$staying->id, $joining->id]);

    expect($result['attached'])->toBe(1);  // joining
    expect($result['detached'])->toBe(1);  // leaving
    expect($team->members()->count())->toBe(2);
});

test('syncRoster writes activity log entry for added members', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();

    $service = new TeamMemberSyncService();
    $service->syncRoster($team, [$member->id]);

    // Manual activity()->log('member_added') writes to the description column, NOT event.
    // The event column is only set automatically by the LogsActivity trait for model lifecycle events.
    $log = Activity::where('log_name', 'teams')
        ->where('description', 'member_added')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['member_id'])->toBe($member->id);
});

test('syncRoster writes activity log entry for removed members', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();
    $team->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $service->syncRoster($team, []);

    // Manual activity()->log('member_removed') writes to the description column, NOT event.
    $log = Activity::where('log_name', 'teams')
        ->where('description', 'member_removed')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['member_id'])->toBe($member->id);
});

// ── syncMemberTeams ───────────────────────────────────────────────────────────

test('syncMemberTeams adds a member to the given teams', function () {
    $team1  = syncTestTeam();
    $team2  = syncTestTeam();
    $member = syncEligibleMember();

    $service = new TeamMemberSyncService();
    $result  = $service->syncMemberTeams($member, [$team1->id, $team2->id]);

    expect($result['added'])->toBe(2);
    expect($result['removed'])->toBe(0);
    expect(DB::table('team_member')->where('member_id', $member->id)->count())->toBe(2);
});

test('syncMemberTeams removes a member from teams not in the given list', function () {
    $team1  = syncTestTeam();
    $team2  = syncTestTeam();
    $member = syncEligibleMember();
    $team1->members()->attach($member->id, ['joined_at' => now()]);
    $team2->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    // Keep only team1 → team2 should be detached.
    $result  = $service->syncMemberTeams($member, [$team1->id]);

    expect($result['removed'])->toBe(1);
    expect(DB::table('team_member')->where('member_id', $member->id)->where('team_id', $team2->id)->count())->toBe(0);
    expect(DB::table('team_member')->where('member_id', $member->id)->where('team_id', $team1->id)->count())->toBe(1);
});

test('syncMemberTeams clears all team assignments when empty list is passed', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();
    $team->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $result  = $service->syncMemberTeams($member, []);

    expect($result['removed'])->toBe(1);
    expect(DB::table('team_member')->where('member_id', $member->id)->count())->toBe(0);
});

test('syncMemberTeams skips inactive teams on add', function () {
    $active   = syncTestTeam(['is_active' => true]);
    $inactive = syncTestTeam(['is_active' => false]);
    $member   = syncEligibleMember();

    $service = new TeamMemberSyncService();
    $result  = $service->syncMemberTeams($member, [$active->id, $inactive->id]);

    // Only the active team should be added.
    expect($result['added'])->toBe(1);
    expect(DB::table('team_member')->where('member_id', $member->id)->where('team_id', $inactive->id)->count())->toBe(0);
    expect(DB::table('team_member')->where('member_id', $member->id)->where('team_id', $active->id)->count())->toBe(1);
});

test('syncMemberTeams skips ineligible member on eligible-only teams', function () {
    $team   = syncTestTeam(['eligible_only' => true]);
    $member = syncIneligibleMember();

    $service = new TeamMemberSyncService();
    $result  = $service->syncMemberTeams($member, [$team->id]);

    expect($result['added'])->toBe(0);
    expect(DB::table('team_member')->where('member_id', $member->id)->count())->toBe(0);
});

test('syncMemberTeams does not re-add a member already in the team', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();
    $team->members()->attach($member->id, ['joined_at' => now()]);

    $service = new TeamMemberSyncService();
    $result  = $service->syncMemberTeams($member, [$team->id]);

    expect($result['added'])->toBe(0);
    expect($result['removed'])->toBe(0);
    // Still exactly one pivot row — no duplicate.
    expect(DB::table('team_member')->where('member_id', $member->id)->where('team_id', $team->id)->count())->toBe(1);
});

test('syncMemberTeams writes activity log entry for added member', function () {
    $team   = syncTestTeam();
    $member = syncEligibleMember();

    $service = new TeamMemberSyncService();
    $service->syncMemberTeams($member, [$team->id]);

    // Manual activity()->log('member_added') writes to the description column, NOT event.
    $log = Activity::where('log_name', 'teams')
        ->where('description', 'member_added')
        ->first();

    expect($log)->not->toBeNull();
    expect($log->properties['member_id'])->toBe($member->id);
});
