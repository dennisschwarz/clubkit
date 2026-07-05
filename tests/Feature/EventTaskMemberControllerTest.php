<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskMember;
use Modules\Members\Models\Member;

// Feature tests in tests/Feature/ automatically use TestCase + RefreshDatabase
// via pest()->extend() in tests/Pest.php.
//
// This file covers two controllers:
//   EventTaskMemberController  → POST/DELETE /events/{event}/members
//   EventSlotController        → POST/DELETE /events/{event}/slots

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',       'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members',    'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'events',     'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'management', 'is_active' => 1, 'installed_at' => now()],
    ]);
    seedPermissions();
});

// ── Auth guard ────────────────────────────────────────────────────────────────
// Note: post()/delete() without Json suffix → 302 redirect to /login.

test('guest cannot assign a member to an event task', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/members")->assertRedirect('/login');
});

test('guest cannot remove a member assignment', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/members/1")->assertRedirect('/login');
});

test('guest cannot create a time-slotted assignment', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/slots")->assertRedirect('/login');
});

test('guest cannot remove a time-slotted assignment', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/slots/1")->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user without events.manage cannot assign a member', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [])
        ->assertStatus(403);
});

test('user without events.manage cannot create a slot', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [])
        ->assertStatus(403);
});

// ── Members endpoint: Store ───────────────────────────────────────────────────

test('user with events.manage can assign a member to an event task (no time window)', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Einlass']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'assignment' => ['id', 'member_id']]);

    $this->assertDatabaseHas('event_task_members', [
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        'time_from'     => null,
        'time_to'       => null,
    ]);
});

test('member store returns 409 when the member is already assigned to this task', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Kasse']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    EventTaskMember::create(['event_task_id' => $task->id, 'member_id' => $member->id]);

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
        ])
        ->assertStatus(409);
});

test('member store returns 422 when task does not belong to this event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $task       = EventTask::create(['event_id' => $otherEvent->id, 'name' => 'Foreign Task']);
    $member     = Member::factory()->create();
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
        ])
        ->assertStatus(422);
});

test('member store returns 422 when event_task_id is missing', function () {
    $event  = Event::factory()->create();
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", ['member_id' => $member->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('event_task_id');
});

// ── Members endpoint: Destroy ─────────────────────────────────────────────────

test('user can remove a task member assignment', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Ordner']);
    $member = Member::factory()->create();
    $etm    = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        // time_from is null = task-tab assignment
    ]);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/members/{$etm->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_task_members', ['id' => $etm->id]);
});

test('member destroy returns 404 when assignment has a time window (use /slots endpoint)', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Aufbau']);
    $member = Member::factory()->create();
    $etm    = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        'time_from'     => '2027-07-15 10:00:00',
        'time_to'       => '2027-07-15 13:00:00',
    ]);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/members/{$etm->id}")
        ->assertStatus(404);

    // Slot must not be deleted
    $this->assertDatabaseHas('event_task_members', ['id' => $etm->id]);
});

// ── Slots endpoint: Store ─────────────────────────────────────────────────────

test('user with events.manage can create a time-slotted assignment', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Kassierer', 'deadline_at' => null]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '10:00',
            'time_to'       => '13:00',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('time_from', '10:00')
        ->assertJsonPath('time_to', '13:00');
});

test('slot store combines event date with H:i time strings to produce full datetime', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Einlass']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '09:00',
            'time_to'       => '11:00',
        ])
        ->assertStatus(201);

    $etm = EventTaskMember::where('event_task_id', $task->id)
        ->where('member_id', $member->id)
        ->firstOrFail();

    expect($etm->time_from->toDateString())->toBe('2027-07-15');
    expect($etm->time_from->format('H:i'))->toBe('09:00');
    expect($etm->time_to->toDateString())->toBe('2027-07-15');
    expect($etm->time_to->format('H:i'))->toBe('11:00');
});

test('slot store returns 422 when task has a future deadline (not an event-day task)', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create([
        'event_id'    => $event->id,
        'name'        => 'Prep Task',
        'deadline_at' => '2027-07-14 10:00:00', // before the event date
    ]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '09:00',
            'time_to'       => '12:00',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

test('slot store returns 422 when task does not belong to this event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $task       = EventTask::create(['event_id' => $otherEvent->id, 'name' => 'Foreign']);
    $member     = Member::factory()->create();
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '09:00',
            'time_to'       => '12:00',
        ])
        ->assertStatus(422);
});

test('slot store returns 422 when time_from is missing', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Test']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_to'       => '12:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_from');
});

test('slot store returns 422 when time_to is before time_from', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Test']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '14:00',
            'time_to'       => '10:00',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_to');
});

test('slot store returns 409 when member is already assigned to this task', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Kassierer']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    EventTaskMember::create(['event_task_id' => $task->id, 'member_id' => $member->id]);

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/slots", [
            'event_task_id' => $task->id,
            'member_id'     => $member->id,
            'time_from'     => '10:00',
            'time_to'       => '13:00',
        ])
        ->assertStatus(409);
});

// ── Slots endpoint: Destroy ───────────────────────────────────────────────────

test('user can remove a time-slotted assignment', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Aufbau']);
    $member = Member::factory()->create();
    $etm    = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        'time_from'     => '2027-07-15 10:00:00',
        'time_to'       => '2027-07-15 13:00:00',
    ]);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/slots/{$etm->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_task_members', ['id' => $etm->id]);
});

test('slot destroy returns 404 when assignment has no time window (use /members endpoint)', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
    $member = Member::factory()->create();
    $etm    = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        // no time_from/time_to = task-tab assignment, not a slot
    ]);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/slots/{$etm->id}")
        ->assertStatus(404);

    // Assignment must not be deleted
    $this->assertDatabaseHas('event_task_members', ['id' => $etm->id]);
});
