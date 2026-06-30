<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberRelation;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ── Create ────────────────────────────────────────────────────────────────────

test('a family relation can be created', function () {
    $father = Member::factory()->create(['first_name' => 'Hans',  'last_name' => 'Müller']);
    $child  = Member::factory()->create(['first_name' => 'Maria', 'last_name' => 'Müller']);

    MemberRelation::create([
        'primary_member_id'   => $father->id,
        'secondary_member_id' => $child->id,
        'relationship'        => 'father',
    ]);

    expect(
        MemberRelation::where('primary_member_id', $father->id)
            ->where('secondary_member_id', $child->id)
            ->where('relationship', 'father')
            ->exists()
    )->toBeTrue();
});

test('all three relationship types can be stored', function () {
    foreach (['father', 'mother', 'sibling'] as $type) {
        $a = Member::factory()->create();
        $b = Member::factory()->create();

        $relation = MemberRelation::create([
            'primary_member_id'   => $a->id,
            'secondary_member_id' => $b->id,
            'relationship'        => $type,
        ]);

        expect($relation->relationship)->toBe($type);
    }
});

// ── Relations ─────────────────────────────────────────────────────────────────

test('primaryMember and secondaryMember resolve correctly', function () {
    $mother = Member::factory()->create(['first_name' => 'Anna']);
    $child  = Member::factory()->create(['first_name' => 'Tom']);

    $relation = MemberRelation::create([
        'primary_member_id'   => $mother->id,
        'secondary_member_id' => $child->id,
        'relationship'        => 'mother',
    ]);

    expect($relation->fresh()->primaryMember->first_name)->toBe('Anna');
    expect($relation->fresh()->secondaryMember->first_name)->toBe('Tom');
});

// ── scopeForMember ────────────────────────────────────────────────────────────

test('scopeForMember finds relations where the member appears on either side', function () {
    $parent    = Member::factory()->create();
    $child1    = Member::factory()->create();
    $child2    = Member::factory()->create();
    $unrelated = Member::factory()->create();

    MemberRelation::create([
        'primary_member_id'   => $parent->id,
        'secondary_member_id' => $child1->id,
        'relationship'        => 'father',
    ]);
    MemberRelation::create([
        'primary_member_id'   => $parent->id,
        'secondary_member_id' => $child2->id,
        'relationship'        => 'father',
    ]);
    MemberRelation::create([
        'primary_member_id'   => $child1->id,
        'secondary_member_id' => $child2->id,
        'relationship'        => 'sibling',
    ]);

    // child1 is primary in the sibling relation → scope finds both relations
    $forChild1 = MemberRelation::forMember($child1->id)->get();
    expect($forChild1)->toHaveCount(2); // parent-child + sibling

    // unrelated has no relations
    expect(MemberRelation::forMember($unrelated->id)->count())->toBe(0);
});

// ── Cascade delete ────────────────────────────────────────────────────────────

test('force-deleting a parent member removes their relations', function () {
    $father = Member::factory()->create();
    $child  = Member::factory()->create();

    MemberRelation::create([
        'primary_member_id'   => $father->id,
        'secondary_member_id' => $child->id,
        'relationship'        => 'father',
    ]);

    $fatherId = $father->id;
    $father->forceDelete(); // physical delete triggers FK cascade

    expect(
        MemberRelation::where('primary_member_id', $fatherId)->exists()
    )->toBeFalse();
});

test('force-deleting a child member removes their relations', function () {
    $mother = Member::factory()->create();
    $child  = Member::factory()->create();

    MemberRelation::create([
        'primary_member_id'   => $mother->id,
        'secondary_member_id' => $child->id,
        'relationship'        => 'mother',
    ]);

    $childId = $child->id;
    $child->forceDelete();

    expect(
        MemberRelation::where('secondary_member_id', $childId)->exists()
    )->toBeFalse();
});
