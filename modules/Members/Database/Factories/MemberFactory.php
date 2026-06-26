<?php

declare(strict_types=1);

namespace Modules\Members\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Members\Models\Member;

/**
 * @extends Factory<Member>
 */
final class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'first_name'            => $this->faker->firstName(),
            'last_name'             => $this->faker->lastName(),
            'date_of_birth'         => $this->faker->dateTimeBetween('-50 years', '-10 years')->format('Y-m-d'),
            'gender'                => $this->faker->randomElement(['male', 'female', 'diverse']),
            'eligible_to_play_date' => now()->subYear()->toDateString(), // standardmäßig berechtigt
            'status'                => 'active',
            'profile_image'         => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    public function notEligible(): static
    {
        // NULL = kein Spielrecht
        return $this->state(['eligible_to_play_date' => null]);
    }

    public function eligibleFrom(string $date): static
    {
        return $this->state(['eligible_to_play_date' => $date]);
    }
}
