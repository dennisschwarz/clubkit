<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;

// TestCase + RefreshDatabase are applied globally for Feature/ via tests/Pest.php.
// No explicit uses() call needed here.

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',       'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members',    'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'events',     'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'management', 'is_active' => 1, 'installed_at' => now()],
    ]);
    // Flush Spatie's permission cache so module permissions registered in
    // ServiceProvider::boot() are visible in the current test request.
    seedPermissions();
});

/**
 * Helper: inserts a management_function row and returns the new ID.
 */
function createFunction(string $name): int
{
    return (int) DB::table('management_functions')->insertGetId([
        'name'       => $name,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/**
 * Helper: inserts a members row and returns the new ID.
 */
function createMember(string $first, string $last): int
{
    return (int) DB::table('members')->insertGetId([
        'first_name' => $first,
        'last_name'  => $last,
        'status'     => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

// ── Assign ────────────────────────────────────────────────────────────────────

test('a member can be assigned to a management function on an event', function () {
    // createUserWithPermission() uses Permission::firstOrCreate() — safe when permission may not yet exist.
    $user = createUserWithPermission('events.manage');

    $event      = Event::factory()->create();
    $functionId = createFunction('Kassier');
    $memberId   = createMember('Max', 'Mustermann');

    $response = $this->actingAs($user)->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => $memberId]
    );

    $response->assertOk()->assertJson(['success' => true]);

    expect(
        DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->where('member_id', $memberId)
            ->exists()
    )->toBeTrue();
});

// ── Upsert (re-assign) ────────────────────────────────────────────────────────

test('re-assigning a function updates the existing record instead of creating a duplicate', function () {
    $user = createUserWithPermission('events.manage');

    $event      = Event::factory()->create();
    $functionId = createFunction('Ordner');
    $memberId1  = createMember('Anna', 'Muster');
    $memberId2  = createMember('Tom',  'Weber');

    $this->actingAs($user)->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => $memberId1]
    );

    $this->actingAs($user)->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => $memberId2]
    );

    expect(
        DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->count()
    )->toBe(1);

    expect(
        DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->value('member_id')
    )->toBe($memberId2);
});

// ── Clear assignment ──────────────────────────────────────────────────────────

test('passing null member_id clears the assignment', function () {
    $user = createUserWithPermission('events.manage');

    $event      = Event::factory()->create();
    $functionId = createFunction('Beisitzer');
    $memberId   = createMember('Sara', 'Klein');

    // First assign
    $this->actingAs($user)->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => $memberId]
    );

    // Then clear
    $response = $this->actingAs($user)->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => null]
    );

    $response->assertOk()->assertJson(['success' => true]);

    expect(
        DB::table('event_management_function')
            ->where('event_id', $event->id)
            ->where('management_function_id', $functionId)
            ->value('member_id')
    )->toBeNull();
});

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $event      = Event::factory()->create();
    $functionId = createFunction('Hausmeister');

    $this->patchJson(
        route('events.functions.assign', ['event' => $event->id, 'functionId' => $functionId]),
        ['member_id' => 1]
    )->assertStatus(401);
});
