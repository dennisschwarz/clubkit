<?php

declare(strict_types=1);

// Note: pivot FK column is still named role_id (legacy schema, not renamed).

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Management\Models\ManagementFunction;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Spatie\Activitylog\Models\Activity;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('a management function can be created', function () {
    $func = ManagementFunction::create(['name' => 'Trainer']);

    expect(ManagementFunction::where('id', $func->id)->where('name', 'Trainer')->exists())->toBeTrue();
});

test('duplicate function names are allowed', function () {
    ManagementFunction::create(['name' => 'Betreuer']);
    ManagementFunction::create(['name' => 'Betreuer']);

    expect(ManagementFunction::where('name', 'Betreuer')->count())->toBe(2);
});

test('created_by is stored correctly', function () {
    $user = User::factory()->create();
    $func = ManagementFunction::create(['name' => 'Kassenwart', 'created_by' => $user->id]);

    expect($func->created_by)->toBe($user->id);
    expect($func->creator->is($user))->toBeTrue();
});

test('created_by may be null', function () {
    $func = ManagementFunction::create(['name' => 'Ordner', 'created_by' => null]);

    expect($func->created_by)->toBeNull();
});

// ── Scopes ────────────────────────────────────────────────────────────────────

test('a function without a team assignment is considered general', function () {
    $func = ManagementFunction::create(['name' => 'Schriftführer']);

    expect($func->teams)->toHaveCount(0);
    expect(ManagementFunction::general()->where('id', $func->id)->exists())->toBeTrue();
});

test('scopeForTeam filters correctly', function () {
    $d1        = Team::factory()->create(['name' => 'D1']);
    $trainer   = ManagementFunction::create(['name' => 'Trainer D1']);
    $general   = ManagementFunction::create(['name' => 'Kassenwart Allgemein']);

    $trainer->teams()->sync([$d1->id]);

    $result = ManagementFunction::forTeam($d1->id)->get();

    expect($result->contains($trainer))->toBeTrue();
    expect($result->contains($general))->toBeFalse();
});

// ── Team assignments ──────────────────────────────────────────────────────────

test('a function can be assigned to multiple teams', function () {
    $func = ManagementFunction::create(['name' => 'Betreuer']);
    $d1   = Team::factory()->create(['name' => 'D1']);
    $d2   = Team::factory()->create(['name' => 'D2']);

    $func->teams()->sync([$d1->id, $d2->id]);

    expect($func->fresh()->teams)->toHaveCount(2);
});

test('deleting a team removes the pivot row but not the function', function () {
    $func = ManagementFunction::create(['name' => 'Trainer']);
    $team = Team::factory()->create();

    $func->teams()->attach($team->id);
    $funcId = $func->id;
    $team->delete();

    expect(
        \Illuminate\Support\Facades\DB::table('management_function_team')
            ->where('role_id', $funcId)->where('team_id', $team->id)->exists()
    )->toBeFalse();
    expect(ManagementFunction::find($funcId))->not->toBeNull();
});

// ── Member assignments ────────────────────────────────────────────────────────

test('multiple members can be assigned to a function', function () {
    $func    = ManagementFunction::create(['name' => 'Betreuer']);
    $mueller = Member::factory()->create(['last_name' => 'Müller']);
    $schmidt = Member::factory()->create(['last_name' => 'Schmidt']);

    $func->members()->sync([$mueller->id, $schmidt->id]);

    expect($func->fresh()->members)->toHaveCount(2);
});

test('a member cannot be assigned to the same function twice', function () {
    $func   = ManagementFunction::create(['name' => 'Trainer']);
    $member = Member::factory()->create();

    $func->members()->attach($member->id);

    expect(fn () => $func->members()->attach($member->id))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Cascade delete ────────────────────────────────────────────────────────────

test('deleting a function cascades to all its pivot assignments', function () {
    $func   = ManagementFunction::create(['name' => 'Trainer']);
    $team   = Team::factory()->create();
    $member = Member::factory()->create();

    $func->teams()->attach($team->id);
    $func->members()->attach($member->id);

    $funcId = $func->id;
    $func->delete();

    expect(\Illuminate\Support\Facades\DB::table('management_function_team')->where('role_id', $funcId)->exists())->toBeFalse();
    expect(\Illuminate\Support\Facades\DB::table('management_function_member')->where('role_id', $funcId)->exists())->toBeFalse();
    expect(ManagementFunction::find($funcId))->toBeNull();
});

test('deleting a function does not delete its members', function () {
    $func   = ManagementFunction::create(['name' => 'Betreuer']);
    $member = Member::factory()->create(['last_name' => 'Bleibt']);

    $func->members()->attach($member->id);
    $func->delete();

    expect(Member::find($member->id))->not->toBeNull();
});

// ── Activity Logging (LogsActivity, Spatie v6) ────────────────────────────────
//
// S20: ClubKit now has a published activity_log migration with the attribute_changes column.
// Spatie ActivityLog v6: when attribute_changes column exists, attribute diffs are stored
// in attribute_changes — NOT in properties. properties only holds custom data (e.g. IP).
//
// CORRECT:   $activity->attribute_changes['attributes']['field']
// INCORRECT: $activity->properties['attributes']['field']   ← was wrong in S11

test('creating a management function writes a created activity log entry', function () {
    $func = ManagementFunction::create(['name' => 'Log Function']);

    $activity = Activity::where('subject_type', ManagementFunction::class)
        ->where('subject_id', $func->id)
        ->where('event', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->log_name)->toBe('management');
});

test('updating a management function writes an updated activity log entry', function () {
    $func = ManagementFunction::create(['name' => 'Original']);
    $func->update(['name' => 'Updated']);

    $activity = Activity::where('subject_type', ManagementFunction::class)
        ->where('subject_id', $func->id)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    // v6 with attribute_changes column: diffs live in attribute_changes, not properties
    expect($activity->attribute_changes['attributes']['name'])->toBe('Updated');
});
