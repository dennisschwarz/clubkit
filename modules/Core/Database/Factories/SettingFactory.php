<?php

declare(strict_types=1);

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Setting;

/**
 * @extends Factory<Setting>
 */
final class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * Default state: a random key-value setting entry.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key'   => fake()->unique()->slug(2),
            'value' => fake()->sentence(3),
        ];
    }

    /**
     * State: create a specific named setting with a known value.
     *
     * Example: Setting::factory()->forKey('club_name', 'FC Beispiel')->create()
     *
     * @param  string $key
     * @param  string $value
     * @return static
     */
    public function forKey(string $key, string $value = ''): static
    {
        return $this->state([
            'key'   => $key,
            'value' => $value,
        ]);
    }
}
