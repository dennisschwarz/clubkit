<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Season;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeasonTest extends TestCase
{
    use RefreshDatabase;

    // ── Casts ─────────────────────────────────────────────────────────────────

    public function test_starts_on_is_cast_to_date(): void
    {
        $season = Season::factory()->make(['starts_on' => '2026-07-01']);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $season->starts_on);
    }

    public function test_is_active_is_cast_to_boolean(): void
    {
        $season = Season::factory()->make(['is_active' => 1]);

        $this->assertTrue($season->is_active);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function test_active_scope_returns_only_active_seasons(): void
    {
        Season::factory()->create(['is_active' => true]);
        Season::factory()->create(['is_active' => false]);

        $active = Season::active()->get();

        $this->assertCount(1, $active);
        $this->assertTrue($active->first()->is_active);
    }

    public function test_active_scope_returns_empty_when_none_active(): void
    {
        Season::factory()->count(3)->create(['is_active' => false]);

        $this->assertCount(0, Season::active()->get());
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function test_season_has_many_teams(): void
    {
        $season = Season::factory()->create();
        Team::factory()->count(3)->create(['season_id' => $season->id]);

        $this->assertCount(3, $season->teams);
    }

    public function test_season_teams_are_ordered_by_sort_order(): void
    {
        $season = Season::factory()->create();
        Team::factory()->create(['season_id' => $season->id, 'sort_order' => 2, 'slug' => 'd2']);
        Team::factory()->create(['season_id' => $season->id, 'sort_order' => 1, 'slug' => 'd1']);

        $teams = $season->teams;

        $this->assertSame(1, $teams->first()->sort_order);
        $this->assertSame(2, $teams->last()->sort_order);
    }
}
