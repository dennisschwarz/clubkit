<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeTeam(array $attrs = []): Team
{
    return Team::create(array_merge([
        'name'           => 'Test Team',
        'color'          => null,
        'is_competition' => false,
        'eligible_only'  => false,
        'season'         => null,
        'league'         => null,
        'age_class'      => null,
        'is_active'      => true,
        'created_by'     => null,
    ], $attrs));
}

function makeEligibleMember(): Member
{
    return Member::create([
        'first_name'            => 'Eligible',
        'last_name'             => 'Player',
        'eligible_to_play_date' => now()->subYear()->toDateString(),
        'status'                => 'active',
        'created_by'            => null,
    ]);
}

function makeIneligibleMember(): Member
{
    return Member::create([
        'first_name'            => 'Ineligible',
        'last_name'             => 'Player',
        'eligible_to_play_date' => null,
        'status'                => 'active',
        'created_by'            => null,
    ]);
}

// ── canAddMember ──────────────────────────────────────────────────────────────

test('canAddMember returns true for any member on a non-eligible-only team', function () {
    $team   = makeTeam(['eligible_only' => false]);
    $member = makeIneligibleMember();

    expect($team->canAddMember($member))->toBeTrue();
});

test('canAddMember returns true for an eligible member on an eligible-only team', function () {
    $team   = makeTeam(['eligible_only' => true]);
    $member = makeEligibleMember();

    expect($team->canAddMember($member))->toBeTrue();
});

test('canAddMember returns false for an ineligible member on an eligible-only team', function () {
    $team   = makeTeam(['eligible_only' => true]);
    $member = makeIneligibleMember();

    expect($team->canAddMember($member))->toBeFalse();
});

test('canAddMember returns false when eligible_to_play_date is in the future', function () {
    $team   = makeTeam(['eligible_only' => true]);
    $member = Member::create([
        'first_name'            => 'Future',
        'last_name'             => 'Eligible',
        'eligible_to_play_date' => now()->addDays(5)->toDateString(),
        'status'                => 'active',
        'created_by'            => null,
    ]);

    // Date is set but in the future → eligible_to_play accessor returns false
    expect($team->canAddMember($member))->toBeFalse();
});

// ── members relation ──────────────────────────────────────────────────────────

test('members relation returns members attached via the team_member pivot', function () {
    $team   = makeTeam();
    $member = makeEligibleMember();

    $team->members()->attach($member->id, ['squad_number' => 7]);

    expect($team->members()->count())->toBe(1);
    expect($team->members()->first()->id)->toBe($member->id);
    expect($team->members()->first()->pivot->squad_number)->toBe(7);
});

test('members relation is empty for a new team', function () {
    $team = makeTeam();

    expect($team->members()->count())->toBe(0);
});

// ── creator() relation ────────────────────────────────────────────────────────

test('creator relation returns the user who created the team', function () {
    $user = User::create([
        'name'     => 'Creator',
        'email'    => 'creator@test.com',
        'password' => bcrypt('secret'),
    ]);

    $team = makeTeam(['created_by' => $user->id]);

    expect($team->creator)->not->toBeNull();
    expect($team->creator->id)->toBe($user->id);
});

test('creator relation is null when created_by is null', function () {
    $team = makeTeam(['created_by' => null]);

    expect($team->creator)->toBeNull();
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S13

test('creating a team writes a created activity log entry', function () {
    $team = makeTeam(['name' => 'Log Team']);

    $activity = Activity::where('subject_type', Team::class)
        ->where('subject_id', $team->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('teams');
});

test('updating a team writes an updated activity log entry', function () {
    $team = makeTeam();
    $team->update(['name' => 'Renamed Team']);

    $activity = Activity::where('subject_type', Team::class)
        ->where('subject_id', $team->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('Renamed Team');
});

test('deleting a team writes a deleted activity log entry', function () {
    $team   = makeTeam();
    $teamId = $team->id;
    $team->delete();

    $activity = Activity::where('subject_type', Team::class)
        ->where('subject_id', $teamId)
        ->where('event', 'deleted')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('teams');
});

test('activity log does not record the created_by field', function () {
    $team = makeTeam(['name' => 'Audit Team']);

    $activity = Activity::where('subject_type', Team::class)
        ->where('subject_id', $team->id)
        ->where('event', 'created')
        ->first();

    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    $attributes = $activity->attribute_changes['attributes'] ?? [];

    expect(array_key_exists('created_by', $attributes))->toBeFalse();
    expect(array_key_exists('name', $attributes))->toBeTrue();
    expect(array_key_exists('is_active', $attributes))->toBeTrue();
});

test('updating only created_by produces no activity log entry', function () {
    // Create a real user so the FK constraint on created_by is satisfied.
    // The purpose of this test is to verify that dontLogEmptyChanges() suppresses
    // entries when only a non-tracked field (created_by) changes.
    $user = User::create([
        'name'     => 'FK User',
        'email'    => 'fk@test.com',
        'password' => bcrypt('secret'),
    ]);

    $team = makeTeam();

    // Clear the 'created' entry
    Activity::where('subject_id', $team->id)->delete();

    // created_by is not in logOnly → must not produce a log entry
    $team->update(['created_by' => $user->id]);

    $count = Activity::where('subject_type', Team::class)
        ->where('subject_id', $team->id)
        ->count();

    expect($count)->toBe(0);
});
