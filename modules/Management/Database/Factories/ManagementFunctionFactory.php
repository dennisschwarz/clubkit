<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\ManagementFunction;

/**
 * @extends Factory<ManagementFunction>
 */
final class ManagementFunctionFactory extends Factory
{
    protected $model = ManagementFunction::class;

    public function definition(): array
    {
        return [
            'name'       => $this->faker->randomElement([
                'Trainer', 'Co-Trainer', 'Betreuer', 'Kassenwart',
                'Schriftführer', 'Ordner', 'Pressewart', 'Sportwart',
            ]),
            'created_by' => null,
        ];
    }
}
