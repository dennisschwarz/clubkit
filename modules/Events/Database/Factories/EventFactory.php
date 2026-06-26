<?php

declare(strict_types=1);

namespace Modules\Events\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Events\Models\Event;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+1 year');

        return [
            'title'       => $this->faker->sentence(3, false),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'starts_at'   => $startsAt,
            'ends_at'     => null,
            'location'    => $this->faker->optional(0.6)->city(),
            'notes'       => null,
            'created_by'  => null,
        ];
    }

    public function withEndTime(): static
    {
        return $this->state(fn (array $attrs) => [
            'ends_at' => (clone $attrs['starts_at'])->modify('+2 hours'),
        ]);
    }

    public function past(): static
    {
        return $this->state([
            'starts_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }
}
