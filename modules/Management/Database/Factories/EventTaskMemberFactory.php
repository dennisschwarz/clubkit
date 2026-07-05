<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\EventTaskMember;

/**
 * @extends Factory<EventTaskMember>
 */
final class EventTaskMemberFactory extends Factory
{
    protected $model = EventTaskMember::class;

    /**
     * Default state: a member assignment without a time window (tasks tab assignment).
     * Both event_task_id and member_id must be provided in tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_task_id' => null,
            'member_id'     => null,
            'time_from'     => null,
            'time_to'       => null,
        ];
    }

    /**
     * Assigns a time window (for Einsatzplan tab assignments).
     *
     * @param  string $from  Datetime string, e.g. '2027-07-15 10:00:00'
     * @param  string $to    Datetime string, e.g. '2027-07-15 13:00:00'
     * @return static
     */
    public function withTimeWindow(string $from, string $to): static
    {
        return $this->state([
            'time_from' => $from,
            'time_to'   => $to,
        ]);
    }
}
