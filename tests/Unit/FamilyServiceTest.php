<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;
use Modules\YouthClubMode\Models\MemberRelation;
use Modules\YouthClubMode\Services\FamilyService;

uses(Tests\TestCase::class, RefreshDatabase::class);

// FamilyService operates on a collection snapshot – no database access required.
// Tests create MemberRelation instances via new (no DB write).

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Creates an in-memory MemberRelation without persisting it.
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
 * Returns a small members map used across multiple tests.
 *
 * @return array<int, array{id: int, name: string, gender: string, date_of_birth: null}>
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

// ── emptyFamily ───────────────────────────────────────────────────────────────

test('emptyFamily returns the correct base structure', function () {
    $service = new FamilyService();
    $empty   = $service->emptyFamily();

    expect($empty)->toHaveKey('father')
        ->and($empty['father'])->toBeNull()
        ->and($empty)->toHaveKey('mother')
        ->and($empty['mother'])->toBeNull()
        ->and($empty['children'])->toBeArray()->toBeEmpty()
        ->and($empty['siblings'])->toBeArray()->toBeEmpty();
});

// ── buildFamilyData – no relations ────────────────────────────────────────────

test('buildFamilyData returns empty family when no relations exist', function () {
    $service = new FamilyService();
    $result  = $service->buildFamilyData(1, new Collection(), membersMap());

    expect($result['father'])->toBeNull()
        ->and($result['mother'])->toBeNull()
        ->and($result['children'])->toBeEmpty()
        ->and($result['siblings'])->toBeEmpty();
});

// ── Father detection ──────────────────────────────────────────────────────────

test('buildFamilyData detects the father of the member', function () {
    // Hans (1) is the father of Tom (3)
    $relations = new Collection([makeRelation(10, 1, 3, 'father')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['father'])->not->toBeNull()
        ->and($family['father']['id'])->toBe(1)
        ->and($family['father']['name'])->toBe('Müller, Hans')
        ->and($family['father']['relation_id'])->toBe(10);
});

// ── Mother detection ──────────────────────────────────────────────────────────

test('buildFamilyData detects the mother of the member', function () {
    // Maria (2) is the mother of Tom (3)
    $relations = new Collection([makeRelation(11, 2, 3, 'mother')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['mother'])->not->toBeNull()
        ->and($family['mother']['id'])->toBe(2)
        ->and($family['mother']['name'])->toBe('Müller, Maria');
});

// ── Children detection ────────────────────────────────────────────────────────

test('buildFamilyData detects the children of the member', function () {
    // Hans (1) is the father of Tom (3) AND Lisa (4)
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

test('buildFamilyData sets parent_relation correctly on child entries', function () {
    // Maria (2) is the mother of Lisa (4)
    $relations = new Collection([makeRelation(13, 2, 4, 'mother')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(2, $relations, membersMap());

    expect($family['children'][0]['parent_relation'])->toBe('mother');
});

// ── Sibling detection ─────────────────────────────────────────────────────────

test('buildFamilyData detects siblings from the primary perspective', function () {
    // Tom (3) and Lisa (4) are siblings; primary = 3
    $relations = new Collection([makeRelation(20, 3, 4, 'sibling')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['siblings'])->toHaveCount(1)
        ->and($family['siblings'][0]['id'])->toBe(4)
        ->and($family['siblings'][0]['name'])->toBe('Müller, Lisa');
});

test('buildFamilyData detects siblings from the secondary perspective', function () {
    // Tom (3) and Lisa (4) are siblings; query from Lisa (secondary)
    $relations = new Collection([makeRelation(20, 3, 4, 'sibling')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(4, $relations, membersMap());

    expect($family['siblings'])->toHaveCount(1)
        ->and($family['siblings'][0]['id'])->toBe(3)
        ->and($family['siblings'][0]['name'])->toBe('Müller, Tom');
});

// ── Full family ───────────────────────────────────────────────────────────────

test('buildFamilyData computes a complete family correctly', function () {
    // Tom (3): father = Hans (1), mother = Maria (2), sister = Lisa (4)
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

// ── Robustness ────────────────────────────────────────────────────────────────

test('buildFamilyData returns question mark when member is not in allMembersJs', function () {
    // Relation with member ID 99, which is not in the map
    $relations = new Collection([makeRelation(99, 99, 3, 'father')]);
    $service   = new FamilyService();

    $family = $service->buildFamilyData(3, $relations, membersMap());

    expect($family['father']['name'])->toBe('?');
});
