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

    /**
     * Default state: a random word value linked to a freshly created definition.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'definition_id' => CustomFieldDefinitionFactory::new(), // FK to custom_field_definitions
            'entity_id'     => $this->faker->numberBetween(1, 100),
            'value'         => $this->faker->word(),
        ];
    }
}
