<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\EventFunction;

/**
 * @extends Factory<EventFunction>
 */
final class EventFunctionFactory extends Factory
{
    protected $model = EventFunction::class;

    /**
     * Default state: an ad-hoc event function without an event or creator.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id'   => null,
            'name'       => $this->faker->randomElement([
                'Fotograf', 'Moderator', 'Begrüßung', 'Technik', 'Catering',
                'Einlass', 'Abrechnung', 'Koordination', 'Protokoll', 'Ansage',
            ]),
            'member_id'  => null,
            'created_by' => null,
        ];
    }
}
