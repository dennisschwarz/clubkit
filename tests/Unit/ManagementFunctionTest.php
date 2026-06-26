<?php

// Ersetzt das obsolete ManagementRoleTest.php.
// ManagementRole wurde zu ManagementFunction umbenannt.
// Pivot-Spalten heißen weiterhin role_id (Tabellenname geändert, Spaltenname nicht).

use Modules\Management\Models\ManagementFunction;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

// ── Anlegen ───────────────────────────────────────────────────────────────────

test('eine Funktion kann angelegt werden', function () {
    $func = ManagementFunction::create(['name' => 'Trainer']);

    $this->assertDatabaseHas('management_functions', ['id' => $func->id, 'name' => 'Trainer']);
});

test('gleicher Funktionsname darf mehrfach existieren', function () {
    ManagementFunction::create(['name' => 'Betreuer']);
    ManagementFunction::create(['name' => 'Betreuer']);

    expect(ManagementFunction::where('name', 'Betreuer')->count())->toBe(2);
});

test('created_by wird korrekt gespeichert', function () {
    $user = App\Models\User::factory()->create();
    $func = ManagementFunction::create(['name' => 'Kassenwart', 'created_by' => $user->id]);

    expect($func->created_by)->toBe($user->id);
    expect($func->creator->is($user))->toBeTrue();
});

test('created_by darf null sein', function () {
    $func = ManagementFunction::create(['name' => 'Ordner', 'created_by' => null]);

    expect($func->created_by)->toBeNull();
});

// ── Scope: Allgemein / Team ───────────────────────────────────────────────────

test('Funktion ohne Team-Zuweisung gilt als allgemein', function () {
    $func = ManagementFunction::create(['name' => 'Schriftführer']);

    expect($func->teams)->toHaveCount(0);
    expect(ManagementFunction::general()->where('id', $func->id)->exists())->toBeTrue();
});

test('scope forTeam filtert korrekt', function () {
    $d1        = Team::factory()->create(['name' => 'D1']);
    $trainer   = ManagementFunction::create(['name' => 'Trainer D1']);
    $allgemein = ManagementFunction::create(['name' => 'Kassenwart Allgemein']);

    $trainer->teams()->sync([$d1->id]);

    $result = ManagementFunction::forTeam($d1->id)->get();

    expect($result->contains($trainer))->toBeTrue();
    expect($result->contains($allgemein))->toBeFalse();
});

// ── Zuweisungen: Teams ────────────────────────────────────────────────────────

test('Funktion kann mehreren Teams zugeordnet werden', function () {
    $func = ManagementFunction::create(['name' => 'Betreuer']);
    $d1   = Team::factory()->create(['name' => 'D1']);
    $d2   = Team::factory()->create(['name' => 'D2']);

    $func->teams()->sync([$d1->id, $d2->id]);

    expect($func->fresh()->teams)->toHaveCount(2);
});

test('beim Löschen eines Teams wird Pivot entfernt aber nicht die Funktion', function () {
    $func = ManagementFunction::create(['name' => 'Trainer']);
    $team = Team::factory()->create();

    $func->teams()->attach($team->id);
    $funcId = $func->id;
    $team->delete();

    $this->assertDatabaseMissing('management_function_team', ['role_id' => $funcId, 'team_id' => $team->id]);
    $this->assertDatabaseHas('management_functions', ['id' => $funcId]);
});

// ── Zuweisungen: Mitglieder ───────────────────────────────────────────────────

test('mehrere Mitglieder können einer Funktion zugewiesen werden', function () {
    $func    = ManagementFunction::create(['name' => 'Betreuer']);
    $mueller = Member::factory()->create(['last_name' => 'Müller']);
    $schmidt = Member::factory()->create(['last_name' => 'Schmidt']);

    $func->members()->sync([$mueller->id, $schmidt->id]);

    expect($func->fresh()->members)->toHaveCount(2);
});

test('eine Person kann dieselbe Funktion nicht doppelt haben', function () {
    $func   = ManagementFunction::create(['name' => 'Trainer']);
    $member = Member::factory()->create();

    $func->members()->attach($member->id);

    expect(fn () => $func->members()->attach($member->id))
        ->toThrow(Illuminate\Database\QueryException::class);
});

// ── Cascade-Delete ────────────────────────────────────────────────────────────

test('beim Löschen einer Funktion werden alle Zuweisungen entfernt', function () {
    $func   = ManagementFunction::create(['name' => 'Trainer']);
    $team   = Team::factory()->create();
    $member = Member::factory()->create();

    $func->teams()->attach($team->id);
    $func->members()->attach($member->id);

    $funcId = $func->id;
    $func->delete();

    $this->assertDatabaseMissing('management_function_team',   ['role_id' => $funcId]);
    $this->assertDatabaseMissing('management_function_member', ['role_id' => $funcId]);
    $this->assertDatabaseMissing('management_functions',       ['id'      => $funcId]);
});

test('das Löschen einer Funktion entfernt nicht die Mitglieder', function () {
    $func   = ManagementFunction::create(['name' => 'Betreuer']);
    $member = Member::factory()->create(['last_name' => 'Bleibt']);

    $func->members()->attach($member->id);
    $func->delete();

    $this->assertDatabaseHas('members', ['id' => $member->id, 'last_name' => 'Bleibt']);
});
