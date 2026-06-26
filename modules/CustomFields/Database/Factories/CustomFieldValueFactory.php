<?php

declare(strict_types=1);

namespace Modules\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CustomFields\Models\CustomFieldValue;

/**
 * @extends Factory<CustomFieldValue>
 */
final class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    public function definition(): array
    {
        return [
            'field_id'   => CustomFieldDefinitionFactory::new(),
            'entity_id'  => $this->faker->numberBetween(1, 100),
            'value'      => $this->faker->word(),
        ];
    }
}
