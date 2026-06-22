<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Member;
use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTest extends TestCase
{
    use RefreshDatabase;

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function test_regular_scope_returns_only_regular_teams(): void
    {
        $season = Season::factory()->create();
        Team::factory()->create(['season_id' => $season->id, 'type' => 'regular', 'slug' => 'd1']);
        Team::factory()->create(['season_id' => $season->id, 'type' => 'trial',   'slug' => 'probe']);
        Team::factory()->create(['season_id' => $season->id, 'type' => 'virtual', 'slug' => 'event']);

        $this->assertCount(1, Team::regular()->get());
    }

    public function test_trial_scope_returns_only_trial_teams(): void
    {
        $season = Season::factory()->create();
        Team::factory()->create(['season_id' => $season->id, 'type' => 'regular', 'slug' => 'd1']);
        Team::factory()->create(['season_id' => $season->id, 'type' => 'trial',   'slug' => 'probe']);

        $this->assertCount(1, Team::trial()->get());
    }

    public function test_ordered_scope_sorts_by_sort_order(): void
    {
        $season = Season::factory()->create();
        Team::factory()->create(['season_id' => $season->id, 'sort_order' => 3, 'slug' => 'c']);
        Team::factory()->create(['season_id' => $season->id, 'sort_order' => 1, 'slug' => 'a']);
        Team::factory()->create(['season_id' => $season->id, 'sort_order' => 2, 'slug' => 'b']);

        $teams = Team::ordered()->get();

        $this->assertSame(1, $teams->first()->sort_order);
        $this->assertSame(3, $teams->last()->sort_order);
    }

    // ── Soft Deletes ──────────────────────────────────────────────────────────

    public function test_team_uses_soft_deletes(): void
    {
        $team = Team::factory()->create();
        $team->delete();

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function test_team_belongs_to_season(): void
    {
        $season = Season::factory()->create();
        $team   = Team::factory()->create(['season_id' => $season->id]);

        $this->assertTrue($team->season->is($season));
    }

    public function test_team_has_members_via_pivot(): void
    {
        $season  = Season::factory()->create();
        $team    = Team::factory()->create(['season_id' => $season->id]);
        $members = Member::factory()->count(3)->create();

        $team->members()->attach($members->pluck('id'));

        $this->assertCount(3, $team->members);
    }

    public function test_duplicate_member_in_same_team_throws_exception(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $season = Season::factory()->create();
        $team   = Team::factory()->create(['season_id' => $season->id]);
        $member = Member::factory()->create();

        $team->members()->attach($member->id);
        $team->members()->attach($member->id); // doppelt → unique constraint
    }
}
