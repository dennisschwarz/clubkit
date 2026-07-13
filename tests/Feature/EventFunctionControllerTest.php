<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventFunction;

// TestCase + RefreshDatabase are applied globally for Feature/ via tests/Pest.php.

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',       'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members',    'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'events',     'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'management', 'is_active' => 1, 'installed_at' => now()],
    ]);
    seedPermissions();
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('a user with events.manage can create an ad-hoc event function', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();

    $response = $this->actingAs($user)->postJson(
        route('events.management.event-functions.store', $event),
        ['name' => 'Fotograf']
    );

    $response->assertOk()->assertJson(['success' => true]);

    expect(
        DB::table('event_functions')
            ->where('event_id', $event->id)
            ->where('name', 'Fotograf')
            ->exists()
    )->toBeTrue();
});

test('store validates that name is required', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();

    $this->actingAs($user)->postJson(
        route('events.management.event-functions.store', $event),
        ['name' => '']
    )->assertUnprocessable();
});

test('store validates that name cannot exceed 255 characters', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();

    $this->actingAs($user)->postJson(
        route('events.management.event-functions.store', $event),
        ['name' => str_repeat('x', 256)]
    )->assertUnprocessable();
});

test('store sets created_by from the authenticated user', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();

    $this->actingAs($user)->postJson(
        route('events.management.event-functions.store', $event),
        ['name' => 'Protokoll']
    );

    $row = DB::table('event_functions')
        ->where('event_id', $event->id)
        ->where('name', 'Protokoll')
        ->first();

    expect((int) $row->created_by)->toBe($user->id);
});

// ── Update ────────────────────────────────────────────────────────────────────

test('a user can assign a member to an ad-hoc function', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Moderator']);

    $memberId = (int) DB::table('members')->insertGetId([
        'first_name' => 'Max', 'last_name' => 'Muster',
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user)->patchJson(
        route('events.management.event-functions.update', ['event' => $event->id, 'eventFunctionId' => $fn->id]),
        ['member_id' => $memberId]
    )->assertOk()->assertJson(['success' => true]);

    expect(EventFunction::find($fn->id)->member_id)->toBe($memberId);
});

test('member_id can be cleared to null', function () {
    $user     = createUserWithPermission('events.manage');
    $event    = Event::factory()->create();
    $memberId = (int) DB::table('members')->insertGetId([
        'first_name' => 'Anna', 'last_name' => 'Klein',
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $fn = EventFunction::create([
        'event_id' => $event->id, 'name' => 'Einlass', 'member_id' => $memberId,
    ]);

    $this->actingAs($user)->patchJson(
        route('events.management.event-functions.update', ['event' => $event->id, 'eventFunctionId' => $fn->id]),
        ['member_id' => null]
    )->assertOk()->assertJson(['success' => true]);

    expect(EventFunction::find($fn->id)->member_id)->toBeNull();
});

test('update returns 404 for a function that belongs to a different event', function () {
    $user   = createUserWithPermission('events.manage');
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();
    $fn     = EventFunction::create(['event_id' => $event1->id, 'name' => 'Technik']);

    $this->actingAs($user)->patchJson(
        route('events.management.event-functions.update', ['event' => $event2->id, 'eventFunctionId' => $fn->id]),
        ['member_id' => null]
    )->assertNotFound();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('a user can delete an ad-hoc event function', function () {
    $user  = createUserWithPermission('events.manage');
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Catering']);
    $fnId  = $fn->id;

    $this->actingAs($user)->deleteJson(
        route('events.management.event-functions.destroy', ['event' => $event->id, 'eventFunctionId' => $fn->id])
    )->assertOk()->assertJson(['success' => true]);

    expect(EventFunction::find($fnId))->toBeNull();
});

test('destroy returns 404 for a function from a different event', function () {
    $user   = createUserWithPermission('events.manage');
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();
    $fn     = EventFunction::create(['event_id' => $event1->id, 'name' => 'Abrechnung']);

    $this->actingAs($user)->deleteJson(
        route('events.management.event-functions.destroy', ['event' => $event2->id, 'eventFunctionId' => $fn->id])
    )->assertNotFound();
});

// ── Auth ──────────────────────────────────────────────────────────────────────

test('unauthenticated store request is rejected with 401', function () {
    $event = Event::factory()->create();

    $this->postJson(
        route('events.management.event-functions.store', $event),
        ['name' => 'Test']
    )->assertStatus(401);
});

test('unauthenticated delete request is rejected with 401', function () {
    $event = Event::factory()->create();
    $fn    = EventFunction::create(['event_id' => $event->id, 'name' => 'Test']);

    $this->deleteJson(
        route('events.management.event-functions.destroy', ['event' => $event->id, 'eventFunctionId' => $fn->id])
    )->assertStatus(401);
});
