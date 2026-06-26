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

    public function definition(): array
    {
        return [
            'name'        => $this->faker->randomElement([
                'Getränkeverkauf', 'Aufbau Tribüne', 'Abbau Tribüne',
                'Kassierer', 'Ordnerdienst', 'Fotograf', 'Videograf',
            ]),
            'description' => $this->faker->optional(0.4)->sentence(),
            'created_by'  => null,
        ];
    }
}
