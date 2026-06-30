<?php

declare(strict_types=1);

namespace Modules\Events\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventTaskMember;
use Modules\Members\Models\Member;

/**
 * @extends Factory<EventTaskMember>
 *
 * Architecture note: ManagementTask is intentionally NOT imported here.
 * Management is an optional module (not in Events' requires[]).
 *
 * task_id is seeded via DB::table('management_tasks') to avoid a hard class
 * dependency. Tests that need a real ManagementTask record should create one
 * explicitly via DB::table() or ManagementTask::factory() when Management
 * is installed in the test environment.
 */
final class EventTaskMemberFactory extends Factory
{
    protected $model = EventTaskMember::class;

    /**
     * Default state: a responsible-person assignment (no time slot).
     *
     * Creates a minimal management_tasks record via DB::table() to populate
     * task_id without importing ManagementTask (optional module).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taskId = DB::table('management_tasks')->insertGetId([
            'name'       => 'Factory Task ' . $this->faker->unique()->word(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'event_id'  => Event::factory(),
            'member_id' => Member::factory(),
            'task_id'   => $taskId,
            'time_from' => null,
            'time_to'   => null,
        ];
    }

    /**
     * State: an assignment with a time window on the event day.
     *
     * The start time defaults to 09:00 and the end time to 12:00.
     * Override via $start / $end (Carbon or datetime string).
     *
     * @param  Carbon|string $start
     * @param  Carbon|string $end
     * @return static
     */
    public function withTimeSlot(
        Carbon|string $start = '2027-07-15 09:00:00',
        Carbon|string $end   = '2027-07-15 12:00:00',
    ): static {
        return $this->state([
            'time_from' => $start,
            'time_to'   => $end,
        ]);
    }
}