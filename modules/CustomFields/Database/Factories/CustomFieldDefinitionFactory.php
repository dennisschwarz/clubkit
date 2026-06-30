<?php

declare(strict_types=1);

namespace Modules\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CustomFields\Models\CustomFieldDefinition;

/**
 * @extends Factory<CustomFieldDefinition>
 */
final class CustomFieldDefinitionFactory extends Factory
{
    protected $model = CustomFieldDefinition::class;

    /**
     * Default state: a random text/number/select/checkbox/date field for a random object type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = $this->faker->words(2, true);

        return [
            'object_type'  => $this->faker->randomElement(['member', 'team', 'event']),
            'label'        => ucwords($label),
            'slug'         => str_replace(' ', '_', strtolower($label)),
            'field_type'   => $this->faker->randomElement(['text', 'number', 'select', 'checkbox', 'date']),
            'options'      => null,
            'placeholder'  => null,
            'is_required'  => false,
            'sort_order'   => $this->faker->numberBetween(0, 100),
            'created_by'   => null,
        ];
    }

    /**
     * Scopes the definition to the 'member' object type.
     *
     * @return static
     */
    public function forMember(): static
    {
        return $this->state(['object_type' => 'member']);
    }

    /**
     * Scopes the definition to the 'team' object type.
     *
     * @return static
     */
    public function forTeam(): static
    {
        return $this->state(['object_type' => 'team']);
    }

    /**
     * Creates a select field with the given option list.
     *
     * @param  list<string> $options
     * @return static
     */
    public function asSelect(array $options = ['Option A', 'Option B', 'Option C']): static
    {
        return $this->state(['field_type' => 'select', 'options' => $options]);
    }

    /**
     * Marks the field as required.
     *
     * @return static
     */
    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }
}
