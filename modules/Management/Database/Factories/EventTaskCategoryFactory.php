<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\EventTaskCategory;

/**
 * @extends Factory<EventTaskCategory>
 */
final class EventTaskCategoryFactory extends Factory
{
    protected $model = EventTaskCategory::class;

    /**
     * Default state: a named category with a random colour and sort_order 0.
     * event_id must be provided in tests via state or create(['event_id' => ...]).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id'   => null,
            'name'       => $this->faker->randomElement([
                'Ordnung', 'Verpflegung', 'Technik', 'Transport',
                'Sicherheit', 'Aufbau', 'Abbau', 'Medien', 'Kasse',
            ]),
            'color'      => $this->faker->randomElement(EventTaskCategory::COLORS),
            'sort_order' => 0,
            'created_by' => null,
        ];
    }

    /**
     * Sets a specific colour slug.
     *
     * @param  string $color  One of EventTaskCategory::COLORS
     * @return static
     */
    public function withColor(string $color): static
    {
        return $this->state(['color' => $color]);
    }

    /**
     * Creates the category without a colour (nullable).
     *
     * @return static
     */
    public function withoutColor(): static
    {
        return $this->state(['color' => null]);
    }
}
