<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;

// ── Module seed ───────────────────────────────────────────────────────────────

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',       'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members',    'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'events',     'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'management', 'is_active' => 1, 'installed_at' => now()],
    ]);
});

// ── Auth guard ────────────────────────────────────────────────────────────────
// Note: post()/delete() without Json suffix → returns 302 redirect to /login.
// postJson()/deleteJson() would return 401 because of the Accept: application/json header.

test('guest cannot assign a member to a task', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/members")->assertRedirect('/login');
});

test('guest cannot remove a member assignment', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/members/1")->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user without events.manage cannot assign a member', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->postJson("/events/{$event->id}/members", [])->assertStatus(403);
});

test('user without events.manage cannot remove a member assignment', function () {
    // A real record is required: route model binding resolves {assignment} before
    // the permission middleware runs, so a non-existent ID would return 404 first.
    $event  = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Task']);
    $member = Member::factory()->create();
    $event->tasks()->attach($task->id);

    $etm = EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]);

    $user = createPlainUser();
    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/members/{$etm->id}")
        ->assertStatus(403);
});

// ── Store: validation ─────────────────────────────────────────────────────────

test('store returns 422 when task_id is missing', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", ['member_id' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('task_id');
});

test('store returns 422 when member_id is missing', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", ['task_id' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('member_id');
});

test('store returns 422 when task is not assigned to the event', function () {
    $event  = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Unlinked Task']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [
            'task_id'   => $task->id,
            'member_id' => $member->id,
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);
});

// ── Store: happy path ─────────────────────────────────────────────────────────

test('user with events.manage can assign a member to an event task', function () {
    $event  = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Kassendienst']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $event->tasks()->attach($task->id);

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/members", [
            'task_id'   => $task->id,
            'member_id' => $member->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['id', 'member_id']);

    $this->assertDatabaseHas('event_task_member', [
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => null,
        'time_to'   => null,
    ]);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user with events.manage can remove a member assignment', function () {
    $event  = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Ordnerdienst']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $event->tasks()->attach($task->id);

    $etm = EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/members/{$etm->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_task_member', ['id' => $etm->id]);
});

test('destroy returns 404 when the assignment belongs to a different event', function () {
    $event1 = Event::factory()->create();
    $event2 = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Aufbau']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $event2->tasks()->attach($task->id);

    $etm = EventTaskMember::create([
        'event_id'  => $event2->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/events/{$event1->id}/members/{$etm->id}")
        ->assertStatus(404);
});

test('destroy returns 404 when the assignment has a time slot (use slots endpoint instead)', function () {
    $event  = Event::factory()->create();
    $task   = ManagementTask::create(['name' => 'Getränke']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $event->tasks()->attach($task->id);

    $etm = EventTaskMember::create([
        'event_id'  => $event->id,
        'task_id'   => $task->id,
        'member_id' => $member->id,
        'time_from' => $event->starts_at->toDateString() . ' 10:00:00',
        'time_to'   => $event->starts_at->toDateString() . ' 12:00:00',
    ]);

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/members/{$etm->id}")
        ->assertStatus(404);
});
