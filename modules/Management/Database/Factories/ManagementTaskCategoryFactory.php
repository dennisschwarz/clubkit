<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\ManagementTaskCategory;

/**
 * @extends Factory<ManagementTaskCategory>
 */
final class ManagementTaskCategoryFactory extends Factory
{
    protected $model = ManagementTaskCategory::class;

    /**
     * Default state: a named category without a creator.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'       => $this->faker->randomElement([
                'Spieltag', 'Turnier', 'Training', 'Vereinsabend', 'Sonstige',
            ]),
            'created_by' => null,
        ];
    }
}
