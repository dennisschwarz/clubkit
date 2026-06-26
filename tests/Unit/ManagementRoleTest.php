<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Management\Models\ManagementRole;
use Modules\Members\Models\Member;
use Modules\Teams\Models\Team;
use Tests\TestCase;

/**
 * ManagementRoleTest
 *
 * Testet das Anlegen, Bearbeiten und Löschen von Management-Rollen
 * sowie die M:N-Relationen zu Teams und Mitgliedern.
 */
class ManagementRoleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function eine_rolle_kann_angelegt_werden(): void
    {
        $role = ManagementRole::create(['name' => 'Trainer', 'created_by' => null]);

        $this->assertDatabaseHas('management_roles', [
            'id'   => $role->id,
            'name' => 'Trainer',
        ]);
    }

    /** @test */
    public function gleicher_rollenname_darf_mehrfach_existieren(): void
    {
        ManagementRole::create(['name' => 'Torwarttrainer']);
        ManagementRole::create(['name' => 'Torwarttrainer']);

        $this->assertSame(
            2,
            ManagementRole::where('name', 'Torwarttrainer')->count()
        );
    }

    /** @test */
    public function eine_rolle_kann_mehreren_teams_zugeordnet_werden(): void
    {
        $role = ManagementRole::create(['name' => 'Torwarttrainer']);
        $d1   = Team::factory()->create(['name' => 'D1-Jugend']);
        $d2   = Team::factory()->create(['name' => 'D2-Jugend']);

        $role->teams()->sync([$d1->id, $d2->id]);

        $this->assertCount(2, $role->fresh()->teams);
        $this->assertTrue($role->fresh()->teams->contains($d1));
        $this->assertTrue($role->fresh()->teams->contains($d2));
    }

    /** @test */
    public function eine_rolle_ohne_team_gilt_als_allgemein(): void
    {
        $role = ManagementRole::create(['name' => 'Kassenwart']);

        $this->assertCount(0, $role->teams);
        $this->assertTrue(
            ManagementRole::general()->where('id', $role->id)->exists()
        );
    }

    /** @test */
    public function scope_for_team_filtert_korrekt(): void
    {
        $d1         = Team::factory()->create(['name' => 'D1']);
        $trainer    = ManagementRole::create(['name' => 'Trainer']);
        $kassenwart = ManagementRole::create(['name' => 'Kassenwart']);

        $trainer->teams()->sync([$d1->id]);

        $result = ManagementRole::forTeam($d1->id)->get();

        $this->assertTrue($result->contains($trainer));
        $this->assertFalse($result->contains($kassenwart));
    }

    /** @test */
    public function mehrere_mitglieder_koennen_einer_rolle_zugewiesen_werden(): void
    {
        $role    = ManagementRole::create(['name' => 'Co-Trainer']);
        $mueller = Member::factory()->create(['last_name' => 'Müller']);
        $schmidt = Member::factory()->create(['last_name' => 'Schmidt']);

        $role->members()->sync([$mueller->id, $schmidt->id]);

        $this->assertCount(2, $role->fresh()->members);
    }

    /** @test */
    public function eine_person_kann_dieselbe_rolle_nicht_doppelt_haben(): void
    {
        $role   = ManagementRole::create(['name' => 'Trainer']);
        $member = Member::factory()->create();

        $role->members()->attach($member->id);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $role->members()->attach($member->id);
    }

    /** @test */
    public function beim_loeschen_einer_rolle_werden_alle_zuweisungen_entfernt(): void
    {
        $role   = ManagementRole::create(['name' => 'Betreuer']);
        $team   = Team::factory()->create();
        $member = Member::factory()->create();

        $role->teams()->attach($team->id);
        $role->members()->attach($member->id);

        $roleId = $role->id;
        $role->delete();

        $this->assertDatabaseMissing('management_role_team',   ['role_id' => $roleId]);
        $this->assertDatabaseMissing('management_role_member', ['role_id' => $roleId]);
        $this->assertDatabaseMissing('management_roles',       ['id'      => $roleId]);
    }

    /** @test */
    public function das_loeschen_einer_rolle_entfernt_nicht_die_mitglieder_selbst(): void
    {
        $role   = ManagementRole::create(['name' => 'Trainer']);
        $member = Member::factory()->create(['last_name' => 'Testperson']);

        $role->members()->attach($member->id);
        $role->delete();

        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    /** @test */
    public function das_loeschen_eines_teams_entfernt_pivot_aber_nicht_die_rolle(): void
    {
        $role = ManagementRole::create(['name' => 'Trainer']);
        $team = Team::factory()->create();

        $role->teams()->attach($team->id);
        $team->delete();

        $this->assertDatabaseMissing('management_role_team', ['team_id' => $team->id]);
        $this->assertDatabaseHas('management_roles', ['id' => $role->id]);
    }

    /** @test */
    public function created_by_wird_korrekt_gespeichert(): void
    {
        $user = \App\Models\User::factory()->create();

        $role = ManagementRole::create([
            'name'       => 'Schatzmeister',
            'created_by' => $user->id,
        ]);

        $this->assertSame($user->id, $role->created_by);
        $this->assertTrue($role->creator->is($user));
    }

    /** @test */
    public function created_by_darf_null_sein(): void
    {
        $role = ManagementRole::create(['name' => 'Pressewart', 'created_by' => null]);

        $this->assertNull($role->created_by);
        $this->assertNull($role->creator);
    }
}
