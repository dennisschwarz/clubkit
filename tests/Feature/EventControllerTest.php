<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;

// ── Module seed ───────────────────────────────────────────────────────────────

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',   'is_active' => 1, 'installed_at' => now()],
        ['slug' => 'members','is_active' => 1, 'installed_at' => now()],
        ['slug' => 'events', 'is_active' => 1, 'installed_at' => now()],
    ]);
});

// ── Auth guard ────────────────────────────────────────────────────────────────

test('guest is redirected to login on GET /events', function () {
    $this->get('/events')->assertRedirect('/login');
});

test('guest is redirected to login on POST /events', function () {
    $this->post('/events')->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────
// Routes use permission:events.manage for all write operations (store/update/destroy)
// and permission:events.view for read operations (index/show).

test('user without permission cannot access GET /events', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/events')->assertStatus(403);
});

test('user without permission cannot create an event', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/events')->assertStatus(403);
});

test('user without permission cannot update an event', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->patch('/events/' . $event->id)->assertStatus(403);
});

test('user without permission cannot delete an event', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->delete('/events/' . $event->id)->assertStatus(403);
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('user with events.view can access the event list', function () {
    Event::factory()->count(2)->create();
    $user = createUserWithPermission('events.view');
    $this->actingAs($user)->get('/events')->assertStatus(200);
});

// ── Store ─────────────────────────────────────────────────────────────────────
// Route uses permission:events.manage (not events.create)

test('user with events.manage can create an event and is redirected to the detail page', function () {
    $user = createUserWithPermission('events.manage');

    $response = $this->actingAs($user)->post('/events', [
        'title'     => 'Jahreshauptversammlung',
        'starts_at' => '2027-01-15 18:00',
        'location'  => 'Vereinsheim',
    ]);

    $this->assertDatabaseHas('events', [
        'title'    => 'Jahreshauptversammlung',
        'location' => 'Vereinsheim',
    ]);

    // store() redirects to the detail page, not back to the list
    $event = Event::where('title', 'Jahreshauptversammlung')->firstOrFail();
    $response->assertRedirect('/events/' . $event->id);
});

test('store returns 422 when title is missing', function () {
    $user = createUserWithPermission('events.manage');
    $this->actingAs($user)->post('/events', [
        'starts_at' => '2027-01-15 18:00',
    ])->assertSessionHasErrors('title');
});

test('store returns 422 when starts_at is missing', function () {
    $user = createUserWithPermission('events.manage');
    $this->actingAs($user)->post('/events', [
        'title' => 'Testtermin',
    ])->assertSessionHasErrors('starts_at');
});

test('store returns 422 when ends_at is before starts_at', function () {
    $user = createUserWithPermission('events.manage');
    $this->actingAs($user)->post('/events', [
        'title'     => 'Testtermin',
        'starts_at' => '2027-01-15 18:00',
        'ends_at'   => '2027-01-14 18:00',
    ])->assertSessionHasErrors('ends_at');
});

// ── Update ────────────────────────────────────────────────────────────────────

test('user with events.manage can update an event and is redirected to the detail page', function () {
    $event = Event::factory()->create(['title' => 'Alt']);
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)->patch('/events/' . $event->id, [
        'title'     => 'Neu',
        'starts_at' => '2027-02-01 10:00',
    ])->assertRedirect('/events/' . $event->id);

    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Neu']);
});

test('user with only events.view cannot update an event', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.view');
    $this->actingAs($user)->patch('/events/' . $event->id, ['title' => 'Neu'])->assertStatus(403);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user with events.manage can delete an event', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.manage');

    $this->actingAs($user)->delete('/events/' . $event->id)
        ->assertRedirect('/events');

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('user without events.manage cannot delete an event', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.view');
    $this->actingAs($user)->delete('/events/' . $event->id)->assertStatus(403);
    $this->assertDatabaseHas('events', ['id' => $event->id]);
});
