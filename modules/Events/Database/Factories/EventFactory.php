<?php

declare(strict_types=1);

namespace Modules\Events\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Events\Models\Event;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Default state: an upcoming event starting within the next 60 days.
     *
     * ends_at is calculated as a relative offset from starts_at so that
     * the Faker dateTimeBetween() "start must be anterior to end" error
     * cannot occur (we never pass two independent strings to dateTimeBetween).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::now()->addDays($this->faker->numberBetween(1, 60));

        return [
            'title'       => $this->faker->randomElement([
                'Heimspiel', 'Auswärtsspiel', 'Training', 'Vereinsfest',
                'Turnier', 'Jahreshauptversammlung', 'Pokalspiel',
            ]) . ' ' . $start->format('d.m.Y'),
            'description' => $this->faker->optional(0.5)->paragraph(),
            'starts_at'   => $start,
            'ends_at'     => $this->faker->boolean(70)
                ? $start->copy()->addHours($this->faker->numberBetween(2, 8))
                : null,
            'location'    => $this->faker->optional(0.8)->city(),
            'notes'       => null,
            'created_by'  => null,
        ];
    }

    /**
     * Creates a past event (started between 1 and 90 days ago).
     *
     * ends_at is a fixed offset after starts_at to avoid date-range issues.
     */
    public function past(): static
    {
        return $this->state(function () {
            $start = Carbon::now()->subDays($this->faker->numberBetween(1, 90));

            return [
                'starts_at' => $start,
                'ends_at'   => $this->faker->boolean(70)
                    ? $start->copy()->addHours($this->faker->numberBetween(2, 8))
                    : null,
            ];
        });
    }

    /**
     * Adds an explicit end time a given number of hours after the start.
     */
    public function withEndTime(int $durationHours = 2): static
    {
        return $this->state(function (array $attrs) use ($durationHours) {
            $start = $attrs['starts_at'] instanceof Carbon
                ? $attrs['starts_at']
                : Carbon::parse($attrs['starts_at']);

            return [
                'ends_at' => $start->copy()->addHours($durationHours),
            ];
        });
    }
}
