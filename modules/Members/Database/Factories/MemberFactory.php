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

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name'            => $this->faker->firstName(),
            'last_name'             => $this->faker->lastName(),
            'date_of_birth'         => $this->faker->dateTimeBetween('-50 years', '-10 years')->format('Y-m-d'),
            'gender'                => $this->faker->randomElement(['male', 'female', 'diverse']),
            'eligible_to_play_date' => now()->subYear()->toDateString(), // eligible by default
            'status'                => 'active',
            'profile_image'         => null,
        ];
    }

    /**
     * Creates a member with status 'inactive'.
     *
     * @return static
     */
    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }

    /**
     * Creates a member with no playing eligibility (eligible_to_play_date = null).
     *
     * @return static
     */
    public function notEligible(): static
    {
        return $this->state(['eligible_to_play_date' => null]);
    }

    /**
     * Creates a member whose eligibility starts on the given date.
     *
     * @param  string $date
     * @return static
     */
    public function eligibleFrom(string $date): static
    {
        return $this->state(['eligible_to_play_date' => $date]);
    }
}
