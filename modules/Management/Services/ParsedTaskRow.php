<?php

declare(strict_types=1);

namespace Modules\Management\Services;

/**
 * Data transfer object representing one parsed row from an event-task CSV import.
 *
 * status is 'ok'      when all required fields pass validation.
 * status is 'invalid' when one or more validation rules fail.
 *
 * errors contains human-readable messages for every failing rule.
 *
 * isSlotTask() returns true when all three slot-config fields are present,
 * causing the import preview to place this row in the "Für Einsatzplan" bucket.
 *
 * toArray() serialises the row for JSON transport to the browser preview.
 */
class ParsedTaskRow
{
    /** @var string 'ok' | 'invalid' */
    public string $status;

    /** @var list<string> Human-readable validation error messages for this row. */
    public array $errors;

    public function __construct(
        public string  $name,
        public ?string $category,
        public string  $priority,
        public ?string $deadline,
        public ?string $notes,
        public ?string $slotStartTime,
        public ?string $slotEndTime,
        public ?int    $slotIntervalMinutes,
        public ?int    $slotCapacity,
        string         $status = 'ok',
        array          $errors = [],
    ) {
        $this->status = $status;
        $this->errors = $errors;
    }

    /**
     * Returns true when this row has complete slot-plan configuration.
     *
     * All three slot-config fields (start, end, interval) must be non-null.
     * Capacity alone does not qualify a row as a slot task.
     */
    public function isSlotTask(): bool
    {
        return $this->slotStartTime !== null
            && $this->slotEndTime !== null
            && $this->slotIntervalMinutes !== null;
    }

    /**
     * Serialises the row to a plain array for JSON transport to the browser.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name'                  => $this->name,
            'category'              => $this->category,
            'priority'              => $this->priority,
            'deadline'              => $this->deadline,
            'notes'                 => $this->notes,
            'slot_start_time'       => $this->slotStartTime,
            'slot_end_time'         => $this->slotEndTime,
            'slot_interval_minutes' => $this->slotIntervalMinutes,
            'slot_capacity'         => $this->slotCapacity,
            'status'                => $this->status,
            'errors'                => $this->errors,
            'is_slot_task'          => $this->isSlotTask(),
        ];
    }
}
