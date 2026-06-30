<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;

beforeEach(function () {
    DB::table('installed_modules')->insertOrIgnore([
        ['slug' => 'core',    'is_active' => 1],
        ['slug' => 'members', 'is_active' => 1],
        ['slug' => 'teams',   'is_active' => 1],
    ]);
    seedPermissions();
});

// ── Auth guard ────────────────────────────────────────────────────────────────

test('gast wird bei GET /teams auf login weitergeleitet', function () {
    $this->get('/teams')->assertRedirect('/login');
});

test('gast wird bei POST /teams auf login weitergeleitet', function () {
    $this->post('/teams')->assertRedirect('/login');
});

// ── Permission guard ──────────────────────────────────────────────────────────

test('user ohne permission kann GET /teams nicht aufrufen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->get('/teams')->assertStatus(403);
});

test('user ohne permission kann keine Teams anlegen', function () {
    $user = createPlainUser();
    $this->actingAs($user)->post('/teams')->assertStatus(403);
});

test('user ohne permission kann keine Teams bearbeiten', function () {
    $team = Team::factory()->create();
    $user = createPlainUser();
    $this->actingAs($user)->patch('/teams/' . $team->id)->assertStatus(403);
});

test('user ohne permission kann keine Teams löschen', function () {
    $team = Team::factory()->create();
    $user = createPlainUser();
    $this->actingAs($user)->delete('/teams/' . $team->id)->assertStatus(403);
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('user mit teams.view sieht Teams-Liste', function () {
    Team::factory()->count(2)->create();
    $user = createUserWithPermission('teams.view');
    $this->actingAs($user)->get('/teams')->assertStatus(200);
});

test('super-admin sieht Teams-Liste ohne explizite Permission', function () {
    $admin = createSuperAdmin();
    $this->actingAs($admin)->get('/teams')->assertStatus(200);
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('user mit teams.manage kann Team anlegen', function () {
    $user = createUserWithPermission('teams.manage');

    $this->actingAs($user)->post('/teams', [
        'name'           => 'Erste Mannschaft',
        'is_competition' => '1',
        'eligible_only'  => '1',
        'is_active'      => '1',
    ])->assertRedirect('/teams');

    $this->assertDatabaseHas('teams', ['name' => 'Erste Mannschaft']);
});

test('store setzt created_by korrekt auf den angemeldeten User', function () {
    $user = createUserWithPermission('teams.manage');

    $this->actingAs($user)->post('/teams', [
        'name'     => 'Test Crew',
        'is_active' => '1',
    ])->assertRedirect('/teams');

    $this->assertDatabaseHas('teams', [
        'name'       => 'Test Crew',
        'created_by' => $user->id,
    ]);
});

test('store gibt 422 bei fehlendem Namen zurück', function () {
    $user = createUserWithPermission('teams.manage');
    $this->actingAs($user)->post('/teams', [])->assertSessionHasErrors('name');
});

test('store gibt 422 bei ungültiger Farbe zurück', function () {
    $user = createUserWithPermission('teams.manage');
    $this->actingAs($user)->post('/teams', [
        'name'  => 'Test',
        'color' => 'neon-pink',
    ])->assertSessionHasErrors('color');
});

// ── Update ────────────────────────────────────────────────────────────────────

test('user mit teams.manage kann Team umbenennen', function () {
    $team = Team::factory()->create(['name' => 'Alt']);
    $user = createUserWithPermission('teams.manage');

    $this->actingAs($user)->patch('/teams/' . $team->id, ['name' => 'Neu'])
        ->assertRedirect('/teams');

    $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Neu']);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('user mit teams.manage kann Team löschen', function () {
    $team = Team::factory()->create();
    $user = createUserWithPermission('teams.manage');

    $this->actingAs($user)->delete('/teams/' . $team->id)
        ->assertRedirect('/teams');

    $this->assertDatabaseMissing('teams', ['id' => $team->id]);
});

// ── Add / remove members ──────────────────────────────────────────────────────

test('user mit teams.members.manage kann Mitglied hinzufügen', function () {
    $team   = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->post('/teams/' . $team->id . '/members', [
        'member_id' => $member->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('team_member', [
        'team_id'   => $team->id,
        'member_id' => $member->id,
    ]);
});

test('nicht spielberechtigtes Mitglied kann nicht zu eligible-only Team hinzugefügt werden', function () {
    $team   = Team::factory()->create(['is_active' => true, 'eligible_only' => true]);
    $member = Member::factory()->notEligible()->create();
    $user   = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->post('/teams/' . $team->id . '/members', [
        'member_id' => $member->id,
    ])->assertRedirect();

    $this->assertDatabaseMissing('team_member', [
        'team_id'   => $team->id,
        'member_id' => $member->id,
    ]);
});

test('user ohne teams.members.manage kann kein Mitglied hinzufügen', function () {
    $team   = Team::factory()->create();
    $member = Member::factory()->create();
    $user   = createUserWithPermission('teams.view');

    $this->actingAs($user)->post('/teams/' . $team->id . '/members', [
        'member_id' => $member->id,
    ])->assertStatus(403);
});

test('user mit teams.members.manage kann Mitglied entfernen', function () {
    $team   = Team::factory()->create();
    $member = Member::factory()->create();
    $team->members()->attach($member->id);
    $user = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->delete('/teams/' . $team->id . '/members/' . $member->id)
        ->assertRedirect();

    $this->assertDatabaseMissing('team_member', [
        'team_id'   => $team->id,
        'member_id' => $member->id,
    ]);
});
