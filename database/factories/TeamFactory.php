<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Season;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'season_id'  => Season::factory(),
            'name'       => ucwords($name),
            'slug'       => Str::slug($name),
            'color'      => $this->faker->hexColor(),
            'type'       => 'regular',
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Reguläres Team (Liga/Wettkampf).
     */
    public function regular(): static
    {
        return $this->state(['type' => 'regular']);
    }

    /**
     * Probetraining-Team.
     */
    public function trial(): static
    {
        return $this->state(['type' => 'trial']);
    }

    /**
     * Virtuelles Team (z.B. Allgemein, Event).
     */
    public function virtual(): static
    {
        return $this->state(['type' => 'virtual']);
    }

    /**
     * Team in einer bestehenden Saison.
     */
    public function forSeason(Season $season): static
    {
        return $this->state(['season_id' => $season->id]);
    }
}
