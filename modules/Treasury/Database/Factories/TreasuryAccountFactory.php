<?php

declare(strict_types=1);

namespace Modules\Treasury\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Treasury\Models\TreasuryAccount;

/**
 * Factory for creating TreasuryAccount test instances.
 *
 * @extends Factory<TreasuryAccount>
 */
class TreasuryAccountFactory extends Factory
{
    protected $model = TreasuryAccount::class;

    /**
     * Returns the default attribute set for a treasury account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(2, true) . ' Kasse',
            'description' => $this->faker->optional()->sentence(),
            'parent_id'   => null,
            'visibility'  => 'public',
            'created_by'  => null,
        ];
    }

    /**
     * Returns a factory state for a team-restricted account.
     */
    public function teamRestricted(): static
    {
        return $this->state(['visibility' => 'team_restricted']);
    }

    /**
     * Returns a factory state for a sub-account of the given parent.
     */
    public function childOf(TreasuryAccount $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
