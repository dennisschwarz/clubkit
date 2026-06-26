<?php

/**
 * EventOrganizerTest → EventAssignmentTest
 *
 * Testet die assignments()-Relation (einmalige Sonder-Aufgaben am Termin).
 * Deckt Randfälle ab, die in EventTest.php nicht explizit getestet werden,
 * insbesondere forceDelete-Cascade.
 */

use Modules\Events\Models\Event;
use Modules\Members\Models\Member;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('ein neues Event hat keine Assignments', function () {
    $event = Event::create(['title' => 'Neues Event', 'starts_at' => now()]);

    expect($event->assignments)->toHaveCount(0);
});

test('mehrere Assignments können einem Termin zugewiesen werden', function () {
    $event = Event::create(['title' => 'Stadtmeisterschaft', 'starts_at' => now()]);
    $ilka  = Member::factory()->create(['first_name' => 'Ilka',  'last_name' => 'Müller']);
    $bernd = Member::factory()->create(['first_name' => 'Bernd', 'last_name' => 'Schmidt']);

    $event->assignments()->sync([$ilka->id, $bernd->id]);

    expect($event->fresh()->assignments)->toHaveCount(2);
});

test('jede Assignment-Person kann eine eigene Beschreibung haben', function () {
    $event = Event::create(['title' => 'Waffelverkauf', 'starts_at' => now()]);
    $ilka  = Member::factory()->create();
    $bernd = Member::factory()->create();

    $event->assignments()->sync([
        $ilka->id  => ['description' => 'Teig machen'],
        $bernd->id => ['description' => 'Backen'],
    ]);

    $fresh = $event->fresh()->assignments->keyBy('id');
    expect($fresh[$ilka->id]->pivot->description)->toBe('Teig machen');
    expect($fresh[$bernd->id]->pivot->description)->toBe('Backen');
});

test('Beschreibung kann nachträglich via sync aktualisiert werden', function () {
    $event  = Event::create(['title' => 'Sommerfest', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->attach($member->id, ['description' => 'Aufbau']);
    $event->assignments()->sync([$member->id => ['description' => 'Abbau']]);

    expect($event->fresh()->assignments->first()->pivot->description)->toBe('Abbau');
});

test('sync verhindert doppelte Assignment-Einträge', function () {
    $event  = Event::create(['title' => 'Event', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->sync([$member->id]);
    $event->assignments()->sync([$member->id]);

    expect($event->fresh()->assignments)->toHaveCount(1);
});

test('Termin löschen entfernt Assignment-Pivot aber nicht das Mitglied', function () {
    $event  = Event::create(['title' => 'Zu löschendes Event', 'starts_at' => now()]);
    $member = Member::factory()->create(['last_name' => 'Bleibt']);

    $event->assignments()->attach($member->id);
    $eventId = $event->id;
    $event->delete();

    $this->assertDatabaseMissing('event_assignments', ['event_id' => $eventId]);
    $this->assertDatabaseHas('members', ['id' => $member->id, 'last_name' => 'Bleibt']);
});

test('permanentes Löschen eines Mitglieds entfernt Assignment-Pivot aber nicht den Termin', function () {
    // SoftDelete löst keinen FK-Cascade aus (Datensatz bleibt physisch).
    // forceDelete() entfernt den Datensatz und triggert den CASCADE.
    $event    = Event::create(['title' => 'Event bleibt', 'starts_at' => now()]);
    $member   = Member::factory()->create();
    $memberId = $member->id;

    $event->assignments()->attach($member->id);
    $member->forceDelete();

    $this->assertDatabaseMissing('event_assignments', ['member_id' => $memberId]);
    $this->assertDatabaseHas('events', ['id' => $event->id]);
});
