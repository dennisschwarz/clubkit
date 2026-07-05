<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;
use Modules\Management\Models\EventTaskMember;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;

// Feature tests in tests/Feature/ automatically use TestCase + RefreshDatabase
// via pest()->extend() in tests/Pest.php.

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
// postJson()/deleteJson() would return 401 due to Accept: application/json header.

test('guest cannot create an event task', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/tasks")->assertRedirect('/login');
});

test('guest cannot complete an event task', function () {
    $event = Event::factory()->create();
    $this->patch("/events/{$event->id}/tasks/1/complete")->assertRedirect('/login');
});

test('guest cannot move an event task', function () {
    $event = Event::factory()->create();
    $this->patch("/events/{$event->id}/tasks/1/move")->assertRedirect('/login');
});

test('guest cannot delete an event task', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/tasks/1")->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user without events.manage cannot create an event task', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", ['name' => 'Test'])
        ->assertStatus(403);
});

test('user without events.manage cannot delete an event task', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
    $user  = createPlainUser();

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/tasks/{$task->id}")
        ->assertStatus(403);
});

// ── Store: direct creation ────────────────────────────────────────────────────

test('user with events.manage can create an event task directly', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", [
            'name'     => 'Einlasskontrolle',
            'priority' => 'important',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('task.name', 'Einlasskontrolle')
        ->assertJsonPath('task.priority', 'important');

    $this->assertDatabaseHas('event_tasks', [
        'event_id' => $event->id,
        'name'     => 'Einlasskontrolle',
        'priority' => 'important',
    ]);
});

test('store returns 422 when neither name nor template_id is provided', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('store response contains all expected task fields', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", ['name' => 'Feldtest'])
        ->assertStatus(201)
        ->assertJsonStructure([
            'success',
            'task' => ['id', 'name', 'priority', 'sort_order', 'category_id', 'completed', 'deadline_at', 'notes', 'template_id'],
        ]);
});

// ── Store: template import ────────────────────────────────────────────────────

test('store copies name from global template when name is omitted', function () {
    $template = ManagementTask::create(['name' => 'Getränkeverkauf', 'priority' => 'normal']);
    $event    = Event::factory()->create();
    $user     = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", [
            'template_id' => $template->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('task.name', 'Getränkeverkauf')
        ->assertJsonPath('task.template_id', $template->id);

    $this->assertDatabaseHas('event_tasks', [
        'event_id'    => $event->id,
        'template_id' => $template->id,
        'name'        => 'Getränkeverkauf',
    ]);
});

test('store uses explicitly provided name even when template_id is given', function () {
    $template = ManagementTask::create(['name' => 'Vorlage']);
    $event    = Event::factory()->create();
    $user     = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", [
            'template_id' => $template->id,
            'name'        => 'Eigener Name',
        ])
        ->assertStatus(201)
        ->assertJsonPath('task.name', 'Eigener Name');
});

// ── Store: category scope guard ───────────────────────────────────────────────

test('store returns 422 when category belongs to a different event', function () {
    $otherEvent = Event::factory()->create();
    $cat        = EventTaskCategory::create(['event_id' => $otherEvent->id, 'name' => 'Foreign Cat']);
    $event      = Event::factory()->create();
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/tasks", [
            'name'        => 'Test Task',
            'category_id' => $cat->id,
        ])
        ->assertStatus(422);
});

// ── Complete ──────────────────────────────────────────────────────────────────

test('user with events.manage can toggle a task to completed', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Aufbau', 'completed' => false]);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/complete")
        ->assertStatus(200)
        ->assertJsonPath('completed', true);

    $this->assertDatabaseHas('event_tasks', ['id' => $task->id, 'completed' => 1]);
});

test('complete toggles from true back to false', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Already Done', 'completed' => true]);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/complete")
        ->assertStatus(200)
        ->assertJsonPath('completed', false);
});

test('complete returns 404 when task does not belong to this event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $task       = EventTask::create(['event_id' => $otherEvent->id, 'name' => 'Foreign Task']);
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/complete")
        ->assertStatus(404);
});

// ── Move ──────────────────────────────────────────────────────────────────────

test('user can move a task to a different category and position', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Aufbau']);
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Stühle']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/move", [
            'category_id' => $cat->id,
            'sort_order'  => 3,
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('event_tasks', [
        'id'          => $task->id,
        'category_id' => $cat->id,
        'sort_order'  => 3,
    ]);
});

test('move with null category_id moves task to uncategorised (Allgemein) section', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Section A']);
    $task  = EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Task']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/move", [
            'category_id' => null,
            'sort_order'  => 0,
        ])
        ->assertStatus(200);

    $this->assertDatabaseHas('event_tasks', ['id' => $task->id, 'category_id' => null]);
});

test('move returns 422 when target category belongs to a different event', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $cat        = EventTaskCategory::create(['event_id' => $otherEvent->id, 'name' => 'Foreign Cat']);
    $task       = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/move", [
            'category_id' => $cat->id,
            'sort_order'  => 0,
        ])
        ->assertStatus(422);
});

test('move returns 422 when sort_order is missing', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Task']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/tasks/{$task->id}/move", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('sort_order');
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user with events.manage can delete an event task', function () {
    $event = Event::factory()->create();
    $task  = EventTask::create(['event_id' => $event->id, 'name' => 'Abbau']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/tasks/{$task->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_tasks', ['id' => $task->id]);
});

test('destroy cascades to member assignments but not to members themselves', function () {
    $event  = Event::factory()->create();
    $member = Member::factory()->create();
    $task   = EventTask::create(['event_id' => $event->id, 'name' => 'Kasse']);
    $etm    = EventTaskMember::create(['event_task_id' => $task->id, 'member_id' => $member->id]);
    $user   = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/tasks/{$task->id}")
        ->assertStatus(200);

    $this->assertDatabaseMissing('event_task_members', ['id' => $etm->id]);
    $this->assertDatabaseHas('members', ['id' => $member->id]);
});

test('destroy returns 404 when task does not belong to this event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $task       = EventTask::create(['event_id' => $otherEvent->id, 'name' => 'Foreign Task']);
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/tasks/{$task->id}")
        ->assertStatus(404);

    // Task must not be deleted
    $this->assertDatabaseHas('event_tasks', ['id' => $task->id]);
});
