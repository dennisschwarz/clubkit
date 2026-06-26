<?php

use Modules\Members\Models\Member;

// ── Auth-Schutz ────────────────────────────────────────────────────────────────

test('gast wird bei GET /members auf login weitergeleitet', function () {
    $this->get('/members')->assertRedirect('/login');
});

test('gast wird bei POST /members auf login weitergeleitet', function () {
    $this->post('/members', [])->assertRedirect('/login');
});

test('gast wird bei PATCH /members/{id} auf login weitergeleitet', function () {
    $member = Member::factory()->create();
    $this->patch('/members/' . $member->id)->assertRedirect('/login');
});

test('gast wird bei DELETE /members/{id} auf login weitergeleitet', function () {
    $member = Member::factory()->create();
    $this->delete('/members/' . $member->id)->assertRedirect('/login');
});

// ── Permission-Schutz ──────────────────────────────────────────────────────────

test('user ohne permission kann GET /members nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/members')->assertStatus(403);
});

test('user ohne permission kann POST /members nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/members', [])->assertStatus(403);
});

test('user ohne permission kann PATCH /members/{id} nicht aufrufen', function () {
    $member = Member::factory()->create();
    $user   = createPlainUser();
    $this->actingAs($user)->patch('/members/' . $member->id)->assertStatus(403);
});

test('user ohne permission kann DELETE /members/{id} nicht aufrufen', function () {
    $member = Member::factory()->create();
    $user   = createPlainUser();
    $this->actingAs($user)->delete('/members/' . $member->id)->assertStatus(403);
});

// ── Index ──────────────────────────────────────────────────────────────────────

test('user mit members.view sieht Mitglieder-Liste', function () {
    Member::factory()->count(3)->create();
    $user = createUserWithPermission('members.view');
    $this->actingAs($user)->get('/members')->assertStatus(200);
});

test('super-admin sieht Mitglieder-Liste ohne explizite Permission', function () {
    $admin = createSuperAdmin();
    $this->actingAs($admin)->get('/members')->assertStatus(200);
});

// ── Store ──────────────────────────────────────────────────────────────────────

test('user mit members.create kann neues Mitglied anlegen', function () {
    $user = createUserWithPermission('members.create');

    $this->actingAs($user)->post('/members', [
        'first_name'       => 'Max',
        'last_name'        => 'Mustermann',
        'status'           => 'active',
        'eligible_to_play' => '1',
    ])->assertRedirect('/members');

    $this->assertDatabaseHas('members', [
        'first_name' => 'Max',
        'last_name'  => 'Mustermann',
        'status'     => 'active',
    ]);
});

test('store gibt 422 bei fehlendem last_name zurück', function () {
    $user = createUserWithPermission('members.create');

    $this->actingAs($user)->post('/members', [
        'first_name' => 'Max',
        'status'     => 'active',
    ])->assertSessionHasErrors('last_name');
});

test('store gibt 422 bei ungültigem status zurück', function () {
    $user = createUserWithPermission('members.create');

    $this->actingAs($user)->post('/members', [
        'first_name' => 'Max',
        'last_name'  => 'Muster',
        'status'     => 'unknown_status',
    ])->assertSessionHasErrors('status');
});

// ── Update ─────────────────────────────────────────────────────────────────────

test('user mit members.edit kann Mitglied aktualisieren', function () {
    $member = Member::factory()->create(['last_name' => 'Alt']);
    $user   = createUserWithPermission('members.edit');

    $this->actingAs($user)->patch('/members/' . $member->id, [
        'first_name'       => $member->first_name,
        'last_name'        => 'Neu',
        'status'           => 'active',
        'eligible_to_play' => '1',
    ])->assertRedirect('/members');

    $this->assertDatabaseHas('members', ['id' => $member->id, 'last_name' => 'Neu']);
});

// ── Destroy ────────────────────────────────────────────────────────────────────

test('user mit members.delete kann Mitglied löschen', function () {
    $member = Member::factory()->create();
    $user   = createUserWithPermission('members.delete');

    $this->actingAs($user)->delete('/members/' . $member->id)
        ->assertRedirect('/members');

    $this->assertSoftDeleted('members', ['id' => $member->id]);
});

test('user mit members.edit aber ohne members.delete kann nicht löschen', function () {
    $member = Member::factory()->create();
    $user   = createUserWithPermission('members.edit');

    $this->actingAs($user)->delete('/members/' . $member->id)->assertStatus(403);
    $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
});
