<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExternalId;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalId>
 */
final class ExternalIdFactory extends Factory
{
    protected $model = ExternalId::class;

    /** Bekannte Verbands-Kürzel */
    private const FEDERATIONS = ['dfbnet', 'handball_net', 'flvw', 'hvw', 'fvm'];

    public function definition(): array
    {
        return [
            'member_id'   => Member::factory(),
            'federation'  => $this->faker->randomElement(self::FEDERATIONS),
            'external_id' => strtoupper($this->faker->bothify('??-#####')),
        ];
    }

    /**
     * DFBnet-ID.
     */
    public function dfbnet(): static
    {
        return $this->state([
            'federation'  => 'dfbnet',
            'external_id' => strtoupper($this->faker->bothify('DFB-#####')),
        ]);
    }

    /**
     * Handball.net-ID.
     */
    public function handball(): static
    {
        return $this->state([
            'federation'  => 'handball_net',
            'external_id' => strtoupper($this->faker->bothify('HB-#####')),
        ]);
    }

    /**
     * Für einen bestehenden Member.
     */
    public function forMember(Member $member): static
    {
        return $this->state(['member_id' => $member->id]);
    }
}
