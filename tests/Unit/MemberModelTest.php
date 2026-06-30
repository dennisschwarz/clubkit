<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Members\Models\Member;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Helper ────────────────────────────────────────────────────────────────────

function makeMember(array $attrs = []): Member
{
    return Member::create(array_merge([
        'first_name'            => 'Test',
        'last_name'             => 'Member',
        'date_of_birth'         => null,
        'gender'                => null,
        'pass_number'           => null,
        'eligible_to_play_date' => null,
        'status'                => 'active',
        'created_by'            => null,
    ], $attrs));
}

// ── eligible_to_play accessor ─────────────────────────────────────────────────
//
// Logic:
//   null              → false  (no date = no eligibility)
//   today             → true   (date is today = eligible)
//   past date         → true   (date in the past = eligible)
//   future date       → false  (date is in the future = not yet eligible)

test('eligible_to_play is false when eligible_to_play_date is null', function () {
    $member = makeMember(['eligible_to_play_date' => null]);

    expect($member->eligible_to_play)->toBeFalse();
});

test('eligible_to_play is true when eligible_to_play_date is today', function () {
    // today() is NOT in the future → accessor returns true
    $member = makeMember(['eligible_to_play_date' => today()->toDateString()]);

    expect($member->eligible_to_play)->toBeTrue();
});

test('eligible_to_play is true when eligible_to_play_date is in the past', function () {
    $member = makeMember(['eligible_to_play_date' => now()->subYear()->toDateString()]);

    expect($member->eligible_to_play)->toBeTrue();
});

test('eligible_to_play is false when eligible_to_play_date is in the future', function () {
    $member = makeMember(['eligible_to_play_date' => now()->addDay()->toDateString()]);

    expect($member->eligible_to_play)->toBeFalse();
});

test('eligible_to_play is correct after re-hydration from database (carbon cast)', function () {
    // Verifies that the 'date' cast produces a Carbon object so that isFuture()
    // works correctly rather than comparing against a raw string.
    $member = makeMember(['eligible_to_play_date' => '2025-01-15']);
    $member = Member::find($member->id); // reload from DB → cast is applied

    expect($member->eligible_to_play)->toBeTrue(); // 2025-01-15 is in the past
});

// ── full_name accessor ────────────────────────────────────────────────────────

test('full_name returns last name comma first name', function () {
    $member = makeMember(['first_name' => 'Maryam', 'last_name' => 'Akhabach']);

    expect($member->full_name)->toBe('Akhabach, Maryam');
});

// ── age accessor ──────────────────────────────────────────────────────────────

test('age calculates the correct age from date_of_birth', function () {
    $dob    = now()->subYears(14)->toDateString();
    $member = makeMember(['date_of_birth' => $dob]);

    expect($member->age)->toBe(14);
});

test('age is null when date_of_birth is null', function () {
    $member = makeMember(['date_of_birth' => null]);

    expect($member->age)->toBeNull();
});

// ── scopeActive ───────────────────────────────────────────────────────────────

test('scopeActive returns only members with status active', function () {
    makeMember(['status' => 'active']);
    makeMember(['status' => 'inactive', 'last_name' => 'Inactive']);

    expect(Member::active()->count())->toBe(1);
});

// ── toJsOption ────────────────────────────────────────────────────────────────

test('toJsOption returns id and formatted name', function () {
    $member = makeMember(['first_name' => 'Hans', 'last_name' => 'Müller']);

    $option = $member->toJsOption();

    expect($option)->toMatchArray([
        'id'   => $member->id,
        'name' => 'Müller, Hans',
    ]);
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S12

test('creating a member writes a created activity log entry', function () {
    $member = makeMember(['first_name' => 'Log', 'last_name' => 'Test']);

    $activity = Activity::where('subject_type', Member::class)
        ->where('subject_id', $member->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('members');
});

test('updating a member writes an updated activity log entry', function () {
    $member = makeMember();
    $member->update(['last_name' => 'Updated']);

    $activity = Activity::where('subject_type', Member::class)
        ->where('subject_id', $member->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['last_name'])->toBe('Updated');
});

test('deleting a member writes a deleted activity log entry', function () {
    $member   = makeMember();
    $memberId = $member->id;
    $member->delete();

    $activity = Activity::where('subject_type', Member::class)
        ->where('subject_id', $memberId)
        ->where('event', 'deleted')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('members');
});

test('activity log only records fields declared in logOnly', function () {
    $member = makeMember(['first_name' => 'Logged']);

    $activity = Activity::where('subject_type', Member::class)
        ->where('subject_id', $member->id)
        ->where('event', 'created')
        ->first();

    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    $attributes = $activity->attribute_changes['attributes'] ?? [];

    // profile_image and created_by must NOT appear (excluded from logOnly list)
    expect(array_key_exists('profile_image', $attributes))->toBeFalse();
    expect(array_key_exists('created_by', $attributes))->toBeFalse();

    // tracked fields must be present
    expect(array_key_exists('first_name', $attributes))->toBeTrue();
    expect(array_key_exists('status', $attributes))->toBeTrue();
});

test('updating a member with no tracked field changes creates no log entry', function () {
    $member = makeMember();

    // Clear the 'created' log entry first
    Activity::where('subject_id', $member->id)->delete();

    // Update only profile_image (not a tracked field per getActivitylogOptions).
    // dontLogEmptyChanges() must suppress this entry.
    $member->update(['profile_image' => 'members/photo.jpg']);

    $count = Activity::where('subject_type', Member::class)
        ->where('subject_id', $member->id)
        ->count();

    expect($count)->toBe(0);
});
