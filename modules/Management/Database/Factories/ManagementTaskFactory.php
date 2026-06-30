<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\ManagementTask;

/**
 * @extends Factory<ManagementTask>
 */
final class ManagementTaskFactory extends Factory
{
    protected $model = ManagementTask::class;

    /**
     * Default state: a named task with normal priority and no category, description, or creator.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->randomElement([
                'Getränkeverkauf', 'Aufbau Tribüne', 'Abbau Tribüne',
                'Kassierer', 'Ordnerdienst', 'Fotograf', 'Videograf',
            ]),
            'description' => $this->faker->optional(0.4)->sentence(),
            'category_id' => null,
            'priority'    => 'normal',
            'created_by'  => null,
        ];
    }

    /**
     * Assigns the task to the given category.
     *
     * @param  int $categoryId
     * @return static
     */
    public function withCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }

    /**
     * Sets the task priority to 'important'.
     *
     * @return static
     */
    public function important(): static
    {
        return $this->state(['priority' => 'important']);
    }

    /**
     * Sets the task priority to 'critical'.
     *
     * @return static
     */
    public function critical(): static
    {
        return $this->state(['priority' => 'critical']);
    }
}
