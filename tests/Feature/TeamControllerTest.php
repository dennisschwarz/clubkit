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
        'name'      => 'Test Crew',
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

// ── Add / remove single member (teams::show page) ─────────────────────────────

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

// ── syncRoster (PUT /{team}/members/sync — Dual Listbox modal) ────────────────

test('user ohne teams.members.manage kann syncRoster nicht aufrufen', function () {
    $team = Team::factory()->create(['is_active' => true]);
    $user = createUserWithPermission('teams.view');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [])
        ->assertStatus(403);
});

test('syncRoster fügt neue Mitglieder in den Kader ein', function () {
    $team    = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $member1 = Member::factory()->create();
    $member2 = Member::factory()->create();
    $user    = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [
        'member_ids' => [$member1->id, $member2->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('team_member', ['team_id' => $team->id, 'member_id' => $member1->id]);
    $this->assertDatabaseHas('team_member', ['team_id' => $team->id, 'member_id' => $member2->id]);
});

test('syncRoster entfernt Mitglieder die nicht mehr im Kader sind', function () {
    $team    = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $staying = Member::factory()->create();
    $leaving = Member::factory()->create();
    $team->members()->attach([$staying->id, $leaving->id], ['joined_at' => now()]);
    $user = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [
        'member_ids' => [$staying->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('team_member',    ['team_id' => $team->id, 'member_id' => $staying->id]);
    $this->assertDatabaseMissing('team_member', ['team_id' => $team->id, 'member_id' => $leaving->id]);
});

test('syncRoster löscht den gesamten Kader wenn keine member_ids übergeben werden', function () {
    $team   = Team::factory()->create(['is_active' => true]);
    $member = Member::factory()->create();
    $team->members()->attach($member->id, ['joined_at' => now()]);
    $user = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [])
        ->assertRedirect();

    $this->assertDatabaseMissing('team_member', ['team_id' => $team->id]);
});

test('syncRoster schlägt fehl bei inaktivem Team', function () {
    $team   = Team::factory()->create(['is_active' => false]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [
        'member_ids' => [$member->id],
    ])->assertRedirect();

    // The redirect back carries an error flash – member must NOT be added.
    $this->assertDatabaseMissing('team_member', ['team_id' => $team->id, 'member_id' => $member->id]);
});

test('syncRoster filtert ineligible Mitglieder bei eligible-only Teams', function () {
    $team      = Team::factory()->create(['is_active' => true, 'eligible_only' => true]);
    $eligible  = Member::factory()->create(['eligible_to_play_date' => now()->subYear()->toDateString()]);
    $ineligible = Member::factory()->notEligible()->create();
    $user      = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)->put('/teams/' . $team->id . '/members/sync', [
        'member_ids' => [$eligible->id, $ineligible->id],
    ])->assertRedirect();

    $this->assertDatabaseHas('team_member',    ['team_id' => $team->id, 'member_id' => $eligible->id]);
    $this->assertDatabaseMissing('team_member', ['team_id' => $team->id, 'member_id' => $ineligible->id]);
});

// ── syncMemberTeams (PUT /teams/member/{member}/sync — AJAX, member modal tab) ─

test('user ohne teams.members.manage kann syncMemberTeams nicht aufrufen', function () {
    $member = Member::factory()->create();
    $user   = createPlainUser();

    $this->actingAs($user)
        ->putJson('/teams/member/' . $member->id . '/sync', ['team_ids' => []])
        ->assertStatus(403);
});

test('syncMemberTeams weist Mitglied mehreren Teams zu (JSON response)', function () {
    $team1  = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $team2  = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)
        ->putJson('/teams/member/' . $member->id . '/sync', [
            'team_ids' => [$team1->id, $team2->id],
        ])
        ->assertOk()
        ->assertJsonStructure(['added', 'removed']);

    $this->assertDatabaseHas('team_member', ['member_id' => $member->id, 'team_id' => $team1->id]);
    $this->assertDatabaseHas('team_member', ['member_id' => $member->id, 'team_id' => $team2->id]);
});

test('syncMemberTeams entfernt Mitglied aus nicht mehr angekreuzten Teams', function () {
    $team1  = Team::factory()->create(['is_active' => true]);
    $team2  = Team::factory()->create(['is_active' => true]);
    $member = Member::factory()->create();
    $team1->members()->attach($member->id, ['joined_at' => now()]);
    $team2->members()->attach($member->id, ['joined_at' => now()]);
    $user = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)
        ->putJson('/teams/member/' . $member->id . '/sync', [
            'team_ids' => [$team1->id],   // team2 deselected
        ])
        ->assertOk()
        ->assertJson(['added' => 0, 'removed' => 1]);

    $this->assertDatabaseMissing('team_member', ['member_id' => $member->id, 'team_id' => $team2->id]);
});

test('syncMemberTeams gibt JSON zurück das added und removed enthält', function () {
    $team   = Team::factory()->create(['is_active' => true, 'eligible_only' => false]);
    $member = Member::factory()->create();
    $user   = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)
        ->putJson('/teams/member/' . $member->id . '/sync', [
            'team_ids' => [$team->id],
        ])
        ->assertOk()
        ->assertJson(['added' => 1, 'removed' => 0]);
});

test('syncMemberTeams überspringt inaktive Teams beim Hinzufügen', function () {
    $inactive = Team::factory()->create(['is_active' => false]);
    $member   = Member::factory()->create();
    $user     = createUserWithPermission('teams.members.manage');

    $this->actingAs($user)
        ->putJson('/teams/member/' . $member->id . '/sync', [
            'team_ids' => [$inactive->id],
        ])
        ->assertOk()
        ->assertJson(['added' => 0]);

    $this->assertDatabaseMissing('team_member', ['member_id' => $member->id, 'team_id' => $inactive->id]);
});
