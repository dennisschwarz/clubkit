<?php

use Modules\Events\Models\Event;
use Modules\Members\Models\Member;

// ── Auth-Schutz ────────────────────────────────────────────────────────────────

test('gast wird bei GET /events auf login weitergeleitet', function () {
    $this->get('/events')->assertRedirect('/login');
});

test('gast wird bei POST /events auf login weitergeleitet', function () {
    $this->post('/events')->assertRedirect('/login');
});

// ── Permission-Schutz ──────────────────────────────────────────────────────────

test('user ohne permission kann GET /events nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/events')->assertStatus(403);
});

test('user ohne permission kann keinen Termin anlegen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/events')->assertStatus(403);
});

test('user ohne permission kann keinen Termin bearbeiten', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->patch('/events/' . $event->id)->assertStatus(403);
});

test('user ohne permission kann keinen Termin löschen', function () {
    $event = Event::factory()->create();
    $user  = createPlainUser();
    $this->actingAs($user)->delete('/events/' . $event->id)->assertStatus(403);
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('user mit events.view sieht Termine-Liste', function () {
    Event::factory()->count(2)->create();
    $user = createUserWithPermission('events.view');
    $this->actingAs($user)->get('/events')->assertStatus(200);
});

// ── Store ──────────────────────────────────────────────────────────────────────

test('user mit events.create kann Termin anlegen', function () {
    $user = createUserWithPermission('events.create');

    $this->actingAs($user)->post('/events', [
        'title'      => 'Jahreshauptversammlung',
        'starts_at'  => '2027-01-15 18:00',
        'location'   => 'Vereinsheim',
    ])->assertRedirect('/events');

    $this->assertDatabaseHas('events', [
        'title'    => 'Jahreshauptversammlung',
        'location' => 'Vereinsheim',
    ]);
});

test('store gibt 422 bei fehlendem Titel zurück', function () {
    $user = createUserWithPermission('events.create');
    $this->actingAs($user)->post('/events', [
        'starts_at' => '2027-01-15 18:00',
    ])->assertSessionHasErrors('title');
});

test('store gibt 422 bei fehlendem starts_at zurück', function () {
    $user = createUserWithPermission('events.create');
    $this->actingAs($user)->post('/events', [
        'title' => 'Testtermin',
    ])->assertSessionHasErrors('starts_at');
});

test('store gibt 422 wenn ends_at vor starts_at liegt', function () {
    $user = createUserWithPermission('events.create');
    $this->actingAs($user)->post('/events', [
        'title'      => 'Testtermin',
        'starts_at'  => '2027-01-15 18:00',
        'ends_at'    => '2027-01-14 18:00',
    ])->assertSessionHasErrors('ends_at');
});

// ── Update ─────────────────────────────────────────────────────────────────────

test('user mit events.edit kann Termin aktualisieren', function () {
    $event = Event::factory()->create(['title' => 'Alt']);
    $user  = createUserWithPermission('events.edit');

    $this->actingAs($user)->patch('/events/' . $event->id, [
        'title'     => 'Neu',
        'starts_at' => '2027-02-01 10:00',
    ])->assertRedirect('/events');

    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Neu']);
});

test('user mit events.view aber ohne events.edit kann nicht bearbeiten', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.view');
    $this->actingAs($user)->patch('/events/' . $event->id, ['title' => 'Neu'])->assertStatus(403);
});

// ── Destroy ────────────────────────────────────────────────────────────────────

test('user mit events.delete kann Termin löschen', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.delete');

    $this->actingAs($user)->delete('/events/' . $event->id)
        ->assertRedirect('/events');

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});

test('user ohne events.delete kann Termin nicht löschen', function () {
    $event = Event::factory()->create();
    $user  = createUserWithPermission('events.view');
    $this->actingAs($user)->delete('/events/' . $event->id)->assertStatus(403);
    $this->assertDatabaseHas('events', ['id' => $event->id]);
});

// ── Assignment-Sync ────────────────────────────────────────────────────────────

test('einmalige Aufgabe wird beim store korrekt gespeichert', function () {
    $person = Member::factory()->create();
    $user   = createUserWithPermission('events.create');

    $this->actingAs($user)->post('/events', [
        'title'      => 'Testtermin',
        'starts_at'  => '2027-03-01 10:00',
        'assignments' => [
            ['member_id' => $person->id, 'description' => 'Schiedsrichter'],
        ],
    ]);

    $event = Event::orderByDesc('id')->first();

    $this->assertDatabaseHas('event_assignments', [
        'event_id'    => $event->id,
        'member_id'   => $person->id,
        'description' => 'Schiedsrichter',
    ]);
});
