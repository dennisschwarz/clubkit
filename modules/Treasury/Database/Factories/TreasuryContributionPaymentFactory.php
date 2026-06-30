<?php

declare(strict_types=1);

namespace Modules\Treasury\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\ManagementTask;
use Modules\Members\Models\Member;
use Modules\Treasury\Models\TreasuryContributionPayment;

/**
 * Factory for creating TreasuryContributionPayment test instances.
 *
 * @extends Factory<TreasuryContributionPayment>
 */
class TreasuryContributionPaymentFactory extends Factory
{
    protected $model = TreasuryContributionPayment::class;

    /**
     * Returns the default attribute set for a contribution payment entry.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id'        => ManagementTask::factory(),
            'member_id'      => Member::factory(),
            'amount'         => $this->faker->randomFloat(2, 10, 200),
            'paid_at'        => null,
            'transaction_id' => null,
            'notes'          => null,
            'created_by'     => null,
        ];
    }

    /**
     * Returns a factory state for a payment that has already been received.
     */
    public function paid(): static
    {
        return $this->state([
            'paid_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }
}
