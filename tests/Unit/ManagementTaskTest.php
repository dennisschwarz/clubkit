<?php

use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Anlegen ───────────────────────────────────────────────────────────────────

test('eine Aufgabe kann angelegt werden', function () {
    $task = ManagementTask::create(['name' => 'Getränkeverkauf', 'created_by' => null]);

    $this->assertDatabaseHas('management_tasks', ['id' => $task->id, 'name' => 'Getränkeverkauf']);
});

test('Aufgabe kann eine Beschreibung haben', function () {
    $task = ManagementTask::create([
        'name'        => 'Aufbau Tribüne',
        'description' => 'Bitte 2 Stunden vor Spielbeginn erscheinen.',
    ]);

    expect($task->name)->toBe('Aufbau Tribüne');
    expect($task->description)->toContain('2 Stunden');
});

test('gleicher Aufgabenname darf mehrfach existieren', function () {
    ManagementTask::create(['name' => 'Ordnerdienst']);
    ManagementTask::create(['name' => 'Ordnerdienst']);

    expect(ManagementTask::where('name', 'Ordnerdienst')->count())->toBe(2);
});

test('created_by wird gespeichert', function () {
    $user = App\Models\User::factory()->create();
    $task = ManagementTask::create(['name' => 'Fotograf', 'created_by' => $user->id]);

    expect($task->created_by)->toBe($user->id);
    expect($task->creator->is($user))->toBeTrue();
});

// ── Scope: Allgemein / Team ───────────────────────────────────────────────────

test('Aufgabe ohne Team-Zuweisung gilt als allgemein', function () {
    $task = ManagementTask::create(['name' => 'Kasse zählen']);

    expect($task->teams)->toHaveCount(0);
    expect(ManagementTask::general()->where('id', $task->id)->exists())->toBeTrue();
});

test('scope forTeam filtert korrekt', function () {
    $d1         = Team::factory()->create(['name' => 'D1']);
    $getraenke  = ManagementTask::create(['name' => 'Getränke D1']);
    $allgemein  = ManagementTask::create(['name' => 'Allgemeine Kasse']);

    $getraenke->teams()->sync([$d1->id]);

    $result = ManagementTask::forTeam($d1->id)->get();

    expect($result->contains($getraenke))->toBeTrue();
    expect($result->contains($allgemein))->toBeFalse();
});

// ── Zuweisungen: Teams ────────────────────────────────────────────────────────

test('Aufgabe kann mehreren Teams zugeordnet werden', function () {
    $task = ManagementTask::create(['name' => 'Getränkeverkauf']);
    $d1   = Team::factory()->create(['name' => 'D1']);
    $d2   = Team::factory()->create(['name' => 'D2']);

    $task->teams()->sync([$d1->id, $d2->id]);

    expect($task->fresh()->teams)->toHaveCount(2);
});

// ── Zuweisungen: Mitglieder ───────────────────────────────────────────────────

test('mehrere Mitglieder können einer Aufgabe zugewiesen werden', function () {
    $task    = ManagementTask::create(['name' => 'Aufbau']);
    $mueller = Member::factory()->create(['last_name' => 'Müller']);
    $schmidt = Member::factory()->create(['last_name' => 'Schmidt']);

    $task->members()->sync([$mueller->id, $schmidt->id]);

    expect($task->fresh()->members)->toHaveCount(2);
});

test('eine Person kann nicht doppelt zu einer Aufgabe zugewiesen werden', function () {
    $task   = ManagementTask::create(['name' => 'Abbau']);
    $member = Member::factory()->create();

    $task->members()->attach($member->id);

    expect(fn () => $task->members()->attach($member->id))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Cascade-Delete ────────────────────────────────────────────────────────────

test('beim Löschen einer Aufgabe werden alle Zuweisungen entfernt', function () {
    $task   = ManagementTask::create(['name' => 'Turnierorganisation']);
    $team   = Team::factory()->create();
    $member = Member::factory()->create();

    $task->teams()->attach($team->id);
    $task->members()->attach($member->id);

    $taskId = $task->id;
    $task->delete();

    $this->assertDatabaseMissing('management_task_team',   ['task_id' => $taskId]);
    $this->assertDatabaseMissing('management_task_member', ['task_id' => $taskId]);
    $this->assertDatabaseMissing('management_tasks',       ['id'      => $taskId]);
});

test('das Löschen einer Aufgabe entfernt nicht die Mitglieder', function () {
    $task   = ManagementTask::create(['name' => 'Kassendienst']);
    $member = Member::factory()->create(['last_name' => 'Testperson']);

    $task->members()->attach($member->id);
    $task->delete();

    $this->assertDatabaseHas('members', ['id' => $member->id]);
});
