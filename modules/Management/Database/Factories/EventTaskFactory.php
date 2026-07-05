<?php

declare(strict_types=1);

namespace Modules\Management\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Management\Models\EventTask;

/**
 * @extends Factory<EventTask>
 */
final class EventTaskFactory extends Factory
{
    protected $model = EventTask::class;

    /**
     * Default state: a named task with normal priority, no category, no template.
     * event_id must be provided in tests via state or create(['event_id' => ...]).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id'    => null,
            'category_id' => null,
            'template_id' => null,
            'name'        => $this->faker->randomElement([
                'Einlasskontrolle', 'Getränkeverkauf', 'Aufbau', 'Abbau',
                'Kassendienst', 'Ordnerdienst', 'Sanitätsdienst', 'Fotografie',
                'Trikot Wäsche', 'Hallenreinigung', 'Material Transport',
            ]),
            'priority'    => 'normal',
            'sort_order'  => 0,
            'deadline_at' => null,
            'completed'   => false,
            'notes'       => null,
            'created_by'  => null,
        ];
    }

    /**
     * Sets the task priority to 'important'.
     *
     * @return static
     */
    public function important(): static
    {
        return $this->state(['priority' => 'important']);
    }

    /**
     * Sets the task priority to 'critical'.
     *
     * @return static
     */
    public function critical(): static
    {
        return $this->state(['priority' => 'critical']);
    }

    /**
     * Marks the task as completed.
     *
     * @return static
     */
    public function completed(): static
    {
        return $this->state(['completed' => true]);
    }

    /**
     * Assigns the task to the given category.
     *
     * @param  int $categoryId
     * @return static
     */
    public function inCategory(int $categoryId): static
    {
        return $this->state(['category_id' => $categoryId]);
    }

    /**
     * Sets this task as imported from a global template.
     *
     * @param  int $templateId  management_tasks.id
     * @return static
     */
    public function fromTemplate(int $templateId): static
    {
        return $this->state(['template_id' => $templateId]);
    }

    /**
     * Sets a deadline (makes this a preparation task, not an event-day task).
     *
     * @param  string $datetime  e.g. '2027-07-10 12:00:00'
     * @return static
     */
    public function withDeadline(string $datetime): static
    {
        return $this->state(['deadline_at' => $datetime]);
    }
}
