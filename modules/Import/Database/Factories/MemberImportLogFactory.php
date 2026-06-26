<?php

declare(strict_types=1);

namespace Modules\Import\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Import\Models\MemberImportLog;

/**
 * @extends Factory<MemberImportLog>
 */
final class MemberImportLogFactory extends Factory
{
    protected $model = MemberImportLog::class;

    public function definition(): array
    {
        return [
            'created_by'    => User::factory(),
            'source'        => $this->faker->randomElement(['dfbnet', 'nuliga']),
            'filename'      => $this->faker->word() . '.csv',
            'created_count' => $this->faker->numberBetween(0, 50),
            'updated_count' => $this->faker->numberBetween(0, 10),
            'skipped_count' => $this->faker->numberBetween(0, 5),
        ];
    }
}
