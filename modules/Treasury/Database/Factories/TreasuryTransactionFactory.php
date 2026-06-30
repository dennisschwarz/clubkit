<?php

declare(strict_types=1);

namespace Modules\Treasury\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryTransaction;

/**
 * Factory for creating TreasuryTransaction test instances.
 *
 * @extends Factory<TreasuryTransaction>
 */
class TreasuryTransactionFactory extends Factory
{
    protected $model = TreasuryTransaction::class;

    /**
     * Returns the default attribute set for a treasury transaction.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id'       => TreasuryAccount::factory(),
            'category_id'      => null,
            'type'             => $this->faker->randomElement(['income', 'expense']),
            'amount'           => $this->faker->randomFloat(2, 1, 1000),
            'description'      => $this->faker->sentence(),
            'transaction_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'reference_number' => $this->faker->optional()->numerify('RE-#####'),
            'member_id'        => null,
            'event_id'         => null,
            'task_id'          => null,
            'created_by'       => null,
        ];
    }

    /**
     * Returns a factory state for income transactions.
     */
    public function income(): static
    {
        return $this->state(['type' => 'income']);
    }

    /**
     * Returns a factory state for expense transactions.
     */
    public function expense(): static
    {
        return $this->state(['type' => 'expense']);
    }
}
