<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Members\Models\Member;
use Modules\YouthClubMode\Models\MemberRelation;

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        'slug' => 'members', 'name' => 'Members', 'version' => '1.0.0',
        'is_active' => true, 'installed_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('installed_modules')->insertOrIgnore([
        'slug' => 'youth-club-mode', 'name' => 'YouthClubMode', 'version' => '1.1.0',
        'is_active' => true, 'installed_at' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(\App\Services\ModuleLoader::class)->seedPermissions('youth-club-mode');
});

// ── Authentication guard ───────────────────────────────────────────────────────

test('gast kann keine familiäre Verbindung anlegen', function () {
    $member = Member::factory()->create();
    $this->post('/members/' . $member->id . '/relations')->assertRedirect('/login');
});

test('gast kann keine familiäre Verbindung löschen', function () {
    $member   = Member::factory()->create();
    $related  = Member::factory()->create();
    $relation = MemberRelation::factory()->create([
        'primary_member_id'   => $member->id,
        'secondary_member_id' => $related->id,
        'relationship'        => 'sibling',
    ]);
    $this->delete('/members/' . $member->id . '/relations/' . $relation->id)->assertRedirect('/login');
});

// ── Permission guard ───────────────────────────────────────────────────────────

test('user ohne youth-club-mode.manage kann keine Verbindung anlegen', function () {
    $member  = Member::factory()->create();
    $related = Member::factory()->create();
    $user    = createPlainUser();

    $this->actingAs($user)->post('/members/' . $member->id . '/relations', [
        'relationship'      => 'sibling',
        'related_member_id' => $related->id,
    ])->assertStatus(403);
});

// ── store: base cases ──────────────────────────────────────────────────────────

test('kann Geschwister-Verbindung anlegen (sibling)', function () {
    $member  = Member::factory()->create();
    $related = Member::factory()->create();
    $user    = createUserWithPermission('youth-club-mode.manage');

    $this->actingAs($user)->postJson('/members/' . $member->id . '/relations', [
        'relationship'      => 'sibling',
        'related_member_id' => $related->id,
    ])->assertStatus(200)->assertJson(['success' => true]);

    $this->assertDatabaseHas('member_relations', [
        'relationship' => 'sibling',
    ]);
});

test('kann Vater-Kind-Verbindung (father) anlegen', function () {
    $kind  = Member::factory()->create();
    $vater = Member::factory()->create();
    $user  = createUserWithPermission('youth-club-mode.manage');

    // child perspective: "this father is my father"
    $this->actingAs($user)->postJson('/members/' . $kind->id . '/relations', [
        'relationship'      => 'father',
        'related_member_id' => $vater->id,
    ])->assertStatus(200)->assertJson(['success' => true]);

    // DB: primary=father, secondary=child
    $this->assertDatabaseHas('member_relations', [
        'primary_member_id'   => $vater->id,
        'secondary_member_id' => $kind->id,
        'relationship'        => 'father',
    ]);
});

test('kann Vater-Kind-Verbindung (father_of) aus Eltern-Perspektive anlegen', function () {
    $vater = Member::factory()->create();
    $kind  = Member::factory()->create();
    $user  = createUserWithPermission('youth-club-mode.manage');

    // father perspective: "I am the father of this child"
    $this->actingAs($user)->postJson('/members/' . $vater->id . '/relations', [
        'relationship'      => 'father_of',
        'related_member_id' => $kind->id,
    ])->assertStatus(200)->assertJson(['success' => true]);

    // DB: primary=father, secondary=child (same layout as 'father' direction)
    $this->assertDatabaseHas('member_relations', [
        'primary_member_id'   => $vater->id,
        'secondary_member_id' => $kind->id,
        'relationship'        => 'father',
    ]);
});

// ── store: validation ──────────────────────────────────────────────────────────

test('store gibt 422 bei fehlendem relationship zurück', function () {
    $member  = Member::factory()->create();
    $related = Member::factory()->create();
    $user    = createUserWithPermission('youth-club-mode.manage');

    $this->actingAs($user)->postJson('/members/' . $member->id . '/relations', [
        'related_member_id' => $related->id,
    ])->assertStatus(422);
});

test('store gibt 422 bei ungültigem relationship zurück', function () {
    $member  = Member::factory()->create();
    $related = Member::factory()->create();
    $user    = createUserWithPermission('youth-club-mode.manage');

    $this->actingAs($user)->postJson('/members/' . $member->id . '/relations', [
        'relationship'      => 'cousin',
        'related_member_id' => $related->id,
    ])->assertStatus(422);
});

test('store verhindert Selbstreferenz', function () {
    $member = Member::factory()->create();
    $user   = createUserWithPermission('youth-club-mode.manage');

    $this->actingAs($user)->postJson('/members/' . $member->id . '/relations', [
        'relationship'      => 'sibling',
        'related_member_id' => $member->id,
    ])->assertStatus(422)->assertJson(['success' => false]);
});

test('store verhindert doppelten Vater', function () {
    $kind   = Member::factory()->create();
    $vater1 = Member::factory()->create();
    $vater2 = Member::factory()->create();
    $user   = createUserWithPermission('youth-club-mode.manage');

    // Create first father
    MemberRelation::create([
        'primary_member_id'   => $vater1->id,
        'secondary_member_id' => $kind->id,
        'relationship'        => 'father',
    ]);

    // Attempt to add second father → must fail
    $this->actingAs($user)->postJson('/members/' . $kind->id . '/relations', [
        'relationship'      => 'father',
        'related_member_id' => $vater2->id,
    ])->assertStatus(422)->assertJson(['success' => false]);
});

test('store verhindert doppelte Geschwister-Verbindung', function () {
    $a    = Member::factory()->create();
    $b    = Member::factory()->create();
    $user = createUserWithPermission('youth-club-mode.manage');

    // Canonical form: lower ID as primary
    $primaryId   = min($a->id, $b->id);
    $secondaryId = max($a->id, $b->id);

    MemberRelation::create([
        'primary_member_id'   => $primaryId,
        'secondary_member_id' => $secondaryId,
        'relationship'        => 'sibling',
    ]);

    // Try again → must fail
    $this->actingAs($user)->postJson('/members/' . $a->id . '/relations', [
        'relationship'      => 'sibling',
        'related_member_id' => $b->id,
    ])->assertStatus(422)->assertJson(['success' => false]);
});

// ── destroy ────────────────────────────────────────────────────────────────────

test('user mit permission kann Verbindung löschen', function () {
    $member  = Member::factory()->create();
    $related = Member::factory()->create();
    $user    = createUserWithPermission('youth-club-mode.manage');

    $relation = MemberRelation::create([
        'primary_member_id'   => $member->id,
        'secondary_member_id' => $related->id,
        'relationship'        => 'sibling',
    ]);

    $this->actingAs($user)->deleteJson('/members/' . $member->id . '/relations/' . $relation->id)
        ->assertStatus(200)->assertJson(['success' => true]);

    $this->assertDatabaseMissing('member_relations', ['id' => $relation->id]);
});

test('destroy verhindert Zugriff wenn Mitglied nicht Teil der Verbindung ist', function () {
    $a       = Member::factory()->create();
    $b       = Member::factory()->create();
    $fremdes = Member::factory()->create();
    $user    = createUserWithPermission('youth-club-mode.manage');

    $relation = MemberRelation::create([
        'primary_member_id'   => $a->id,
        'secondary_member_id' => $b->id,
        'relationship'        => 'sibling',
    ]);

    // An unrelated member tries to delete the relation between a and b
    $this->actingAs($user)->deleteJson('/members/' . $fremdes->id . '/relations/' . $relation->id)
        ->assertStatus(403)->assertJson(['success' => false]);

    $this->assertDatabaseHas('member_relations', ['id' => $relation->id]);
});
