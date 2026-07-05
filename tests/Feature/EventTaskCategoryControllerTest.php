<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Management\Models\EventTask;
use Modules\Management\Models\EventTaskCategory;

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
// Note: post()/patch()/delete() without Json suffix → 302 redirect to /login.

test('guest cannot create an event task category', function () {
    $event = Event::factory()->create();
    $this->post("/events/{$event->id}/task-categories")->assertRedirect('/login');
});

test('guest cannot update an event task category', function () {
    $event = Event::factory()->create();
    $this->patch("/events/{$event->id}/task-categories/1")->assertRedirect('/login');
});

test('guest cannot delete an event task category', function () {
    $event = Event::factory()->create();
    $this->delete("/events/{$event->id}/task-categories/1")->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user without events.manage cannot create a category', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", ['name' => 'Test'])
        ->assertStatus(403);
});

test('user without events.manage cannot delete a category', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Test']);
    $user  = createPlainUser();

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/task-categories/{$cat->id}")
        ->assertStatus(403);
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('user with events.manage can create a category with name and colour', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", [
            'name'  => 'Aufbau',
            'color' => 'blue',
        ])
        ->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('category.name', 'Aufbau')
        ->assertJsonPath('category.color', 'blue');

    $this->assertDatabaseHas('event_task_categories', [
        'event_id' => $event->id,
        'name'     => 'Aufbau',
        'color'    => 'blue',
    ]);
});

test('store creates a category without colour (nullable)', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", ['name' => 'Kasse'])
        ->assertStatus(201)
        ->assertJsonPath('category.color', null);

    $this->assertDatabaseHas('event_task_categories', [
        'event_id' => $event->id,
        'name'     => 'Kasse',
        'color'    => null,
    ]);
});

test('store response contains id, name, color and sort_order', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", ['name' => 'Structure Test'])
        ->assertStatus(201)
        ->assertJsonStructure(['success', 'category' => ['id', 'name', 'color', 'sort_order']]);
});

test('store returns 422 when name is missing', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('store returns 422 when colour is not a valid slug', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", [
            'name'  => 'Invalid Color',
            'color' => 'not-a-color',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('color');
});

test('store assigns event_id from route — not from request body', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->postJson("/events/{$event->id}/task-categories", ['name' => 'Scoped'])
        ->assertStatus(201);

    $this->assertDatabaseHas('event_task_categories', [
        'event_id' => $event->id,
        'name'     => 'Scoped',
    ]);
});

// ── Update ────────────────────────────────────────────────────────────────────

test('user can update a category name and colour', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Old Name', 'color' => 'gray']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/task-categories/{$cat->id}", [
            'name'  => 'New Name',
            'color' => 'green',
        ])
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseHas('event_task_categories', [
        'id'    => $cat->id,
        'name'  => 'New Name',
        'color' => 'green',
    ]);
});

test('update returns 422 when name is missing', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Test']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/task-categories/{$cat->id}", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('update returns 404 when category belongs to a different event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $cat        = EventTaskCategory::create(['event_id' => $otherEvent->id, 'name' => 'Foreign']);
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->patchJson("/events/{$event->id}/task-categories/{$cat->id}", ['name' => 'Hack'])
        ->assertStatus(404);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user can delete a category', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Ordnung']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/task-categories/{$cat->id}")
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('event_task_categories', ['id' => $cat->id]);
});

test('destroy response contains moved_count of tasks reassigned to Allgemein', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'Einlass']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Gate 1']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Gate 2']);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/task-categories/{$cat->id}")
        ->assertStatus(200)
        ->assertJsonPath('moved_count', 2);

    // Tasks still exist with category_id set to null (DB SET NULL constraint)
    expect(EventTask::where('event_id', $event->id)->whereNull('category_id')->count())->toBe(2);
});

test('destroy does not delete tasks in the category', function () {
    $event = Event::factory()->create();
    $cat   = EventTaskCategory::create(['event_id' => $event->id, 'name' => 'VIP']);
    EventTask::create(['event_id' => $event->id, 'category_id' => $cat->id, 'name' => 'Backstage']);
    $user = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/task-categories/{$cat->id}")
        ->assertStatus(200);

    expect(EventTask::where('event_id', $event->id)->count())->toBe(1);
});

test('destroy returns 404 when category belongs to a different event (IDOR guard)', function () {
    $event      = Event::factory()->create();
    $otherEvent = Event::factory()->create();
    $cat        = EventTaskCategory::create(['event_id' => $otherEvent->id, 'name' => 'Foreign']);
    $user       = createUserWithPermission('events.manage');

    $this->actingAs($user)
        ->deleteJson("/events/{$event->id}/task-categories/{$cat->id}")
        ->assertStatus(404);

    $this->assertDatabaseHas('event_task_categories', ['id' => $cat->id]);
});
