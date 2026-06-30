<?php

declare(strict_types=1);

namespace Modules\Treasury\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\ManagementTask;
use Modules\Treasury\Models\TreasuryAccount;
use Modules\Treasury\Models\TreasuryTaskMeta;

/**
 * Factory for creating TreasuryTaskMeta test instances.
 *
 * @extends Factory<TreasuryTaskMeta>
 */
class TreasuryTaskMetaFactory extends Factory
{
    protected $model = TreasuryTaskMeta::class;

    /**
     * Returns the default attribute set for a treasury task meta record.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id'        => ManagementTask::factory(),
            'account_id'     => TreasuryAccount::factory(),
            'default_amount' => $this->faker->randomFloat(2, 10, 200),
            'due_date'       => $this->faker->optional()->dateTimeBetween('now', '+6 months')?->format('Y-m-d'),
            'created_by'     => null,
        ];
    }
}
