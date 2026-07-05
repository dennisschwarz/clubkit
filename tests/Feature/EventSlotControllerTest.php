<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskMember;
use Modules\Members\Models\Member;

// ── Module seed ───────────────────────────────────────────────────────────────

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
// Note: post()/delete() without Json suffix → returns 302 redirect to /login.
// postJson()/deleteJson() would return 401 because of the Accept: application/json header.

test('guest cannot create a time slot', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/slots")->assertRedirect('/login');
});

test('guest cannot delete a time slot', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/slots/1")->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user without events.manage cannot create a time slot', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->postJson("/events/{$event->id}/slots", [])->assertStatus(403);
});

// ── Store: validation ─────────────────────────────────────────────────────────

test('store returns 422 when time_from is missing', function () {
    $event  = Event::factory()->create();
    // EventTask replaces the old ManagementTask + event_task pivot approach.
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
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

test('store returns 422 when time_to is before time_from', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
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

// ── Store: business rules ─────────────────────────────────────────────────────

test('store returns 422 when the task has a future deadline (not an event-day task)', function () {
    $event          = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $futureDeadline = '2027-07-14 10:00:00'; // one day before event — not an event-day task
    $task           = EventTask::create([
        'event_id'    => $event->id,
        'name'        => 'Vorbereitungsaufgabe',
        'deadline_at' => $futureDeadline,
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

// ── Store: happy path ─────────────────────────────────────────────────────────

test('user with events.manage can assign a member with a time slot to an event-day task', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    // deadline_at = null → event-day task, eligible for time slots.
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
        ->assertJsonPath('time_to',   '13:00');

    $this->assertDatabaseHas('event_task_members', [
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
    ]);
});

test('store combines the event date with the H:i time string to produce a full datetime', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Einlasskontrolle', 'deadline_at' => null]);
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

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user with events.manage can remove a time slot', function () {
    $event  = Event::factory()->create(['starts_at' => Carbon::parse('2027-07-15 14:00:00')]);
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Aufbau']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    $slot = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
        'time_from'     => '2027-07-15 10:00:00',
        'time_to'       => '2027-07-15 13:00:00',
    ]);

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/slots/{$slot->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_task_members', ['id' => $slot->id]);
});

test('destroy returns 404 when the record has no time slot (use members endpoint instead)', function () {
    $event  = Event::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('events.manage');

    // Task-tab assignment: no time_from / time_to — must use /members/{id} endpoint.
    $etm = EventTaskMember::create([
        'event_task_id' => $task->id,
        'member_id'     => $member->id,
    ]);

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/slots/{$etm->id}")
        ->assertStatus(404);
});
