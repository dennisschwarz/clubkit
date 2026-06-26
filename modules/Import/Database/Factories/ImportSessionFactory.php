<?php

declare(strict_types=1);

namespace Modules\Import\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Import\Models\ImportSession;

/**
 * @extends Factory<ImportSession>
 */
final class ImportSessionFactory extends Factory
{
    protected $model = ImportSession::class;

    public function definition(): array
    {
        return [
            'created_by'     => User::factory(),
            'source'         => $this->faker->randomElement(['dfbnet', 'nuliga']),
            'filename'       => $this->faker->word() . '.csv',
            'column_headers' => ['Vorname', 'Nachname', 'Geb.', 'Passnummer'],
            'raw_rows'       => [],
            'samples'        => [
                'Vorname'    => ['Maryam', 'Anna'],
                'Nachname'   => ['Akhabach', 'Müller'],
                'Geb.'       => ['08.09.2012'],
                'Passnummer' => ['0765-0056'],
            ],
            'mapping'        => null,
            'processed_rows' => null,
            'expires_at'     => now()->addHours(2),
        ];
    }

    /** Session ist abgelaufen */
    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subHour()]);
    }

    /** Session hat bereits Mapping + verarbeitete Zeilen */
    public function withProcessedRows(array $rows = []): static
    {
        $default = [
            0 => [
                'mapped'       => ['first_name' => 'Maryam', 'last_name' => 'Akhabach', 'pass_number' => '0765-0001'],
                'status'       => 'new',
                'existing_id'  => null,
                'diff'         => [],
                'custom_fields'=> [],
            ],
        ];

        return $this->state([
            'mapping'        => ['Vorname' => 'first_name', 'Nachname' => 'last_name'],
            'processed_rows' => $rows ?: $default,
        ]);
    }
}
