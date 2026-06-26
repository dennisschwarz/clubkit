<?php

use Illuminate\Database\Eloquent\Collection;
use Modules\YouthClubMode\Models\MemberRelation;
use Modules\YouthClubMode\Services\FamilyService;

// FamilyService arbeitet rein auf einem Collection-Snapshot – kein DB-Zugriff nötig.
// Tests erstellen MemberRelation-Objekte via make() (kein DB-Write).

// ── Hilfsfunktion ──────────────────────────────────────────────────────────────

/**
 * Erzeugt eine MemberRelation ohne DB (make-only).
 */
function makeRelation(int $id, int $primaryId, int $secondaryId, string $relationship): MemberRelation
{
    $r                      = new MemberRelation();
    $r->id                  = $id;
    $r->primary_member_id   = $primaryId;
    $r->secondary_member_id = $secondaryId;
    $r->relationship        = $relationship;
    return $r;
}

/**
 * Kleines allMembersJs-Array für Tests.
 */
function membersMap(): array
{
    return [
        1 => ['id' => 1, 'name' => 'Müller, Hans',  'gender' => 'm', 'date_of_birth' => null],
        2 => ['id' => 2, 'name' => 'Müller, Maria', 'gender' => 'f', 'date_of_birth' => null],
        3 => ['id' => 3, 'name' => 'Müller, Tom',   'gender' => 'm', 'date_of_birth' => null],
        4 => ['id' => 4, 'name' => 'Müller, Lisa',  'gender' => 'f', 'date_of_birth' => null],
    ];
}

// ── emptyFamily ────────────────────────────────────────────────────────────────

test('emptyFamily gibt korrekte Grundstruktur zurück', function () {
    $service = new FamilyService();
    $empty   = $service->emptyFamily();

    expect($empty)->toHaveKey('father')
        ->and($empty['father'])->toBeNull()
        ->and($empty)->toHaveKey('mother')
        ->and($empty['mother'])->toBeNull()
        ->and($empty['children'])->toBeArray()->toBeEmpty()
        ->and($empty['siblings'])->toBeArray()->toBeEmpty();
});

// ── buildFamilyData – keine Relationen ────────────────────────────────────────

test('buildFamilyData gibt leere Familie zurück wenn keine Relationen existieren', function () {
    $service  = new FamilyService();
    $result   = $service->buildFamilyData(1, new Collection(), membersMap());

    expect($result['father'])->toBeNull()
        ->and($result['mother'])->toBeNull()
        ->and($result['children'])->toBeEmpty()
        ->and($result['siblings'])->toBeEmpty();
});

// ── Vater erkennen ─────────────────────────────────────────────────────────────

test('buildFamilyData erkennt den Vater des Mitglieds', function () {
    // Hans (1) ist Vater von Tom (3)
    $relations = new Collection([makeRelation(10, 1, 3, 'father')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['father'])->not->toBeNull()
        ->and($family['father']['id'])->toBe(1)
        ->and($family['father']['name'])->toBe('Müller, Hans')
        ->and($family['father']['relation_id'])->toBe(10);
});

// ── Mutter erkennen ────────────────────────────────────────────────────────────

test('buildFamilyData erkennt die Mutter des Mitglieds', function () {
    // Maria (2) ist Mutter von Tom (3)
    $relations = new Collection([makeRelation(11, 2, 3, 'mother')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['mother'])->not->toBeNull()
        ->and($family['mother']['id'])->toBe(2)
        ->and($family['mother']['name'])->toBe('Müller, Maria');
});

// ── Kinder erkennen ────────────────────────────────────────────────────────────

test('buildFamilyData erkennt Kinder des Mitglieds', function () {
    // Hans (1) ist Vater von Tom (3) UND Lisa (4)
    $relations = new Collection([
        makeRelation(10, 1, 3, 'father'),
        makeRelation(12, 1, 4, 'father'),
    ]);
    $service = new FamilyService();

    $family = $service->buildFamilyData(1, $relations, membersMap());

    expect($family['children'])->toHaveCount(2);
    $ids = array_column($family['children'], 'id');
    expect($ids)->toContain(3)->toContain(4);
});

test('buildFamilyData setzt parent_relation korrekt bei Kind-Eintrag', function () {
    // Maria (2) ist Mutter von Lisa (4)
    $relations = new Collection([makeRelation(13, 2, 4, 'mother')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(2, $relations, membersMap());

    expect($family['children'][0]['parent_relation'])->toBe('mother');
});

// ── Geschwister erkennen ───────────────────────────────────────────────────────

test('buildFamilyData erkennt Geschwister (primary Perspektive)', function () {
    // Tom (3) und Lisa (4) sind Geschwister; primary = 3
    $relations = new Collection([makeRelation(20, 3, 4, 'sibling')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['siblings'])->toHaveCount(1)
        ->and($family['siblings'][0]['id'])->toBe(4)
        ->and($family['siblings'][0]['name'])->toBe('Müller, Lisa');
});

test('buildFamilyData erkennt Geschwister (secondary Perspektive)', function () {
    // Tom (3) und Lisa (4) sind Geschwister; Abfrage aus Lisa-Perspektive (secondary)
    $relations = new Collection([makeRelation(20, 3, 4, 'sibling')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(4, $relations, membersMap());

    expect($family['siblings'])->toHaveCount(1)
        ->and($family['siblings'][0]['id'])->toBe(3)
        ->and($family['siblings'][0]['name'])->toBe('Müller, Tom');
});

// ── Kombination ────────────────────────────────────────────────────────────────

test('buildFamilyData berechnet vollständige Familie korrekt', function () {
    // Tom (3): Vater = Hans (1), Mutter = Maria (2), Schwester = Lisa (4)
    $relations = new Collection([
        makeRelation(10, 1, 3, 'father'),
        makeRelation(11, 2, 3, 'mother'),
        makeRelation(20, 3, 4, 'sibling'),
    ]);
    $service = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['father']['id'])->toBe(1)
        ->and($family['mother']['id'])->toBe(2)
        ->and($family['children'])->toBeEmpty()
        ->and($family['siblings'])->toHaveCount(1)
        ->and($family['siblings'][0]['id'])->toBe(4);
});

// ── Robustheit: Unbekannter Member in allMembersJs ────────────────────────────

test('buildFamilyData gibt Fragezeichen zurück wenn Mitglied nicht in allMembersJs', function () {
    // Relation mit Member-ID 99, die nicht in der Map ist
    $relations = new Collection([makeRelation(99, 99, 3, 'father')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['father']['name'])->toBe('?');
});
