<?php

declare(strict_types=1);

namespace Modules\Teams\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Teams\Models\Team;

/**
 * @extends Factory<Team>
 */
final class TeamFactory extends Factory
{
    protected $model = Team::class;

    /**
     * Default state: active, non-competition team without eligibility restriction.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'           => ucwords($this->faker->unique()->words(2, true)),
            'color'          => null,
            'is_competition' => false,
            'eligible_only'  => false,
            'season'         => null,
            'league'         => null,
            'age_class'      => null,
            'is_active'      => true,
            'created_by'     => null,
        ];
    }

    /**
     * Creates a competition team that requires playing eligibility for all members.
     *
     * @return static
     */
    public function competition(): static
    {
        return $this->state(['is_competition' => true, 'eligible_only' => true]);
    }

    /**
     * Creates a team with is_active = false.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
