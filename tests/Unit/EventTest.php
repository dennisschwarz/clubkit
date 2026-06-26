<?php

use Illuminate\Support\Carbon;
use Modules\Events\Models\Event;
use Modules\Management\Models\ManagementFunction;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Anlegen ───────────────────────────────────────────────────────────────────

test('ein termin kann angelegt werden', function () {
    $event = Event::create(['title' => 'Weihnachtsfeier', 'starts_at' => '2026-12-20 18:00:00']);

    expect($event->id)->not->toBeNull();
    $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Weihnachtsfeier']);
});

test('starts_at wird als Carbon-Objekt gecastet', function () {
    $event = Event::create(['title' => 'Sommerfest', 'starts_at' => '2026-07-04 15:00:00']);

    expect($event->fresh()->starts_at)->toBeInstanceOf(Carbon::class);
    expect($event->fresh()->starts_at->format('Y-m-d'))->toBe('2026-07-04');
});

test('ends_at ist optional und standardmäßig null', function () {
    $event = Event::create(['title' => 'Teammeeting', 'starts_at' => now()]);

    expect($event->ends_at)->toBeNull();
});

test('created_by wird gespeichert und creator()-Relation gibt Ersteller zurück', function () {
    $user  = App\Models\User::factory()->create();
    $event = Event::create(['title' => 'Turnier', 'starts_at' => now(), 'created_by' => $user->id]);

    expect($event->fresh()->creator->id)->toBe($user->id);
});

// ── assignments()-Relation (Einmalige Sonder-Assignments) ─────────────────────

test('Mitglieder können als Assignments hinzugefügt werden', function () {
    $event = Event::create(['title' => 'Waffelverkauf', 'starts_at' => now()]);
    $ilka  = Member::factory()->create(['first_name' => 'Ilka',  'last_name' => 'Müller']);
    $bernd = Member::factory()->create(['first_name' => 'Bernd', 'last_name' => 'Schmidt']);

    $event->assignments()->sync([$ilka->id, $bernd->id]);

    expect($event->fresh()->assignments)->toHaveCount(2);
});

test('Assignment kann eine Beschreibung erhalten', function () {
    $event  = Event::create(['title' => 'Turnier', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->sync([
        $member->id => ['description' => 'Schiedsrichter']
    ]);

    expect($event->fresh()->assignments->first()->pivot->description)
        ->toBe('Schiedsrichter');
});

test('Beschreibung im Assignment ist optional (null)', function () {
    $event  = Event::create(['title' => 'Grillfest', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->attach($member->id);

    expect($event->fresh()->assignments->first()->pivot->description)
        ->toBeNull();
});

test('dieselbe Person kann nicht doppelt als Assignment eingetragen werden', function () {
    $event  = Event::create(['title' => 'Kaffeeklatsch', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->attach($member->id);

    expect(fn () => $event->assignments()->attach($member->id))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── managementFunctions()-Relation ────────────────────────────────────────────

test('managementFunctions()-Relation kann Vereinsfunktionen zuordnen', function () {
    $event = Event::create(['title' => 'Vereinsturnier', 'starts_at' => now()]);
    $fn1   = ManagementFunction::factory()->create(['name' => 'Trainer']);
    $fn2   = ManagementFunction::factory()->create(['name' => 'Betreuer']);

    $event->managementFunctions()->sync([$fn1->id, $fn2->id]);

    expect($event->fresh()->managementFunctions)->toHaveCount(2);
});

test('managementFunctions() gibt leere Collection wenn keine Funktion zugeordnet', function () {
    $event = Event::create(['title' => 'Meeting', 'starts_at' => now()]);

    expect($event->managementFunctions)->toHaveCount(0);
});

test('managementFunctions() kann via sync aktualisiert werden', function () {
    $event = Event::create(['title' => 'Pokalspiel', 'starts_at' => now()]);
    $fn1   = ManagementFunction::factory()->create(['name' => 'Trainer']);
    $fn2   = ManagementFunction::factory()->create(['name' => 'Schiedsrichter']);

    $event->managementFunctions()->sync([$fn1->id, $fn2->id]);
    $event->managementFunctions()->sync([$fn1->id]);

    expect($event->fresh()->managementFunctions)->toHaveCount(1);
    expect($event->fresh()->managementFunctions->first()->id)->toBe($fn1->id);
});

test('ManagementFunction trägt ihre Mitglieder mit (via Relation)', function () {
    $event = Event::create(['title' => 'Sommerfest', 'starts_at' => now()]);
    $fn    = ManagementFunction::factory()->create(['name' => 'Trainer']);
    $ilka  = Member::factory()->create(['last_name' => 'Müller']);
    $bernd = Member::factory()->create(['last_name' => 'Schmidt']);

    $fn->members()->sync([$ilka->id, $bernd->id]);
    $event->managementFunctions()->sync([$fn->id]);

    $loaded = $event->fresh()->managementFunctions()->with('members')->first();
    expect($loaded->members)->toHaveCount(2);
});

// ── teams()-Relation ──────────────────────────────────────────────────────────

test('teams()-Relation kann Teams hinzufügen', function () {
    $event = Event::create(['title' => 'Vereinsturnier', 'starts_at' => now()]);
    $team1 = Team::factory()->create(['name' => 'A-Jugend']);
    $team2 = Team::factory()->create(['name' => 'B-Jugend']);

    $event->teams()->sync([$team1->id, $team2->id]);

    expect($event->fresh()->teams)->toHaveCount(2);
});

// ── tasks()-Relation ──────────────────────────────────────────────────────────

test('tasks()-Relation kann Aufgaben verknüpfen', function () {
    $event = Event::create(['title' => 'Sommerfest', 'starts_at' => now()]);
    $task1 = ManagementTask::factory()->create(['name' => 'Aufbau']);
    $task2 = ManagementTask::factory()->create(['name' => 'Abbau']);

    $event->tasks()->sync([$task1->id, $task2->id]);

    expect($event->fresh()->tasks)->toHaveCount(2);
});

// ── Cascade-Delete ────────────────────────────────────────────────────────────

test('beim Löschen eines Termins werden Assignment-Pivots entfernt', function () {
    $event  = Event::create(['title' => 'Abschlussfest', 'starts_at' => now()]);
    $member = Member::factory()->create();

    $event->assignments()->attach($member->id);
    $eventId = $event->id;
    $event->delete();

    $this->assertDatabaseMissing('event_assignments', ['event_id' => $eventId]);
    $this->assertDatabaseMissing('events',            ['id'       => $eventId]);
});

test('beim Löschen eines Termins bleibt das Mitglied erhalten', function () {
    $event  = Event::create(['title' => 'Turnier', 'starts_at' => now()]);
    $member = Member::factory()->create(['last_name' => 'Bleibt']);

    $event->assignments()->attach($member->id);
    $event->delete();

    $this->assertDatabaseHas('members', ['id' => $member->id]);
});

test('beim Löschen eines Termins werden Funktions-Pivots entfernt', function () {
    $event = Event::create(['title' => 'Pokalendspiel', 'starts_at' => now()]);
    $fn    = ManagementFunction::factory()->create();

    $event->managementFunctions()->attach($fn->id);
    $eventId = $event->id;
    $event->delete();

    $this->assertDatabaseMissing('event_management_function', ['event_id' => $eventId]);
});

test('beim Löschen eines Termins werden Team-Pivots entfernt', function () {
    $event = Event::create(['title' => 'Pokalendspiel', 'starts_at' => now()]);
    $team  = Team::factory()->create();

    $event->teams()->attach($team->id);
    $eventId = $event->id;
    $event->delete();

    $this->assertDatabaseMissing('event_team', ['event_id' => $eventId]);
});

test('beim Löschen eines Termins werden Aufgaben-Pivots entfernt', function () {
    $event = Event::create(['title' => 'Jahresfeier', 'starts_at' => now()]);
    $task  = ManagementTask::factory()->create(['name' => 'Catering']);

    $event->tasks()->attach($task->id);
    $eventId = $event->id;
    $event->delete();

    $this->assertDatabaseMissing('event_task', ['event_id' => $eventId]);
});
