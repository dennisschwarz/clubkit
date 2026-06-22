<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
final class SeasonFactory extends Factory
{
    protected $model = Season::class;

    public function definition(): array
    {
        $year = $this->faker->numberBetween(2024, 2030);

        return [
            'name'      => "{$year}/" . ($year + 1),
            'starts_on' => "{$year}-07-01",
            'ends_on'   => ($year + 1) . '-06-30',
            'is_active' => false,
        ];
    }

    /**
     * Aktive Saison.
     */
    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    /**
     * Inaktive Saison.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
