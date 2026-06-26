<?php

declare(strict_types=1);

namespace Modules\YouthClubMode\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Members\Database\Factories\MemberFactory;
use Modules\YouthClubMode\Models\MemberRelation;

/**
 * @extends Factory<MemberRelation>
 */
final class MemberRelationFactory extends Factory
{
    protected $model = MemberRelation::class;

    public function definition(): array
    {
        return [
            'primary_member_id'   => MemberFactory::new(),
            'secondary_member_id' => MemberFactory::new(),
            'relationship'        => $this->faker->randomElement(['father', 'mother', 'sibling']),
            'created_by'          => null,
        ];
    }

    public function father(): static
    {
        return $this->state(['relationship' => 'father']);
    }

    public function mother(): static
    {
        return $this->state(['relationship' => 'mother']);
    }

    public function sibling(): static
    {
        return $this->state(['relationship' => 'sibling']);
    }
}
