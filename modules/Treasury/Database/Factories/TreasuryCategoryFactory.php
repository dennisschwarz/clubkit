<?php

declare(strict_types=1);

namespace Modules\Treasury\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Treasury\Models\TreasuryCategory;

/**
 * Factory for creating TreasuryCategory test instances.
 *
 * @extends Factory<TreasuryCategory>
 */
class TreasuryCategoryFactory extends Factory
{
    protected $model = TreasuryCategory::class;

    /**
     * Returns the default attribute set for a treasury category.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'             => $this->faker->word(),
            'transaction_type' => $this->faker->randomElement(['income', 'expense']),
            'color'            => $this->faker->randomElement(['green', 'red', 'blue', 'gray', null]),
            'created_by'       => null,
        ];
    }

    /**
     * Returns a factory state for income categories.
     */
    public function income(): static
    {
        return $this->state(['transaction_type' => 'income']);
    }

    /**
     * Returns a factory state for expense categories.
     */
    public function expense(): static
    {
        return $this->state(['transaction_type' => 'expense']);
    }
}
