<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'first_name'    => $this->faker->firstName(),
            'last_name'     => $this->faker->lastName(),
            'phone'         => $this->faker->optional(0.8)->phoneNumber(),
            'email'         => $this->faker->optional(0.7)->safeEmail(),
            'street'        => $this->faker->optional(0.9)->streetName(),
            'street_number' => $this->faker->optional(0.9)->buildingNumber(),
            'postal_code'   => $this->faker->optional(0.9)->postcode(),
            'city'          => $this->faker->optional(0.9)->city(),
        ];
    }

    /**
     * Kontakt mit vollständiger Adresse.
     */
    public function withFullAddress(): static
    {
        return $this->state([
            'street'        => $this->faker->streetName(),
            'street_number' => $this->faker->buildingNumber(),
            'postal_code'   => $this->faker->postcode(),
            'city'          => $this->faker->city(),
        ]);
    }

    /**
     * Kontakt ohne Adressdaten.
     */
    public function withoutAddress(): static
    {
        return $this->state([
            'street'        => null,
            'street_number' => null,
            'postal_code'   => null,
            'city'          => null,
        ]);
    }
}
