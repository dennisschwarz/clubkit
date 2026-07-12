<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Management\Models\EventTask;

/**
 * Validates the JSON payload for the import execute step.
 *
 * The browser sends only the tasks the user has selected and confirmed
 * in the interactive preview. Each task in the 'tasks' array is validated
 * individually with the same rules as EventTask model constraints.
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class EventTaskImportRequest extends FormRequest
{
    /** @return bool */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'tasks'                         => ['required', 'array', 'min:1'],
            'tasks.*.name'                  => ['required', 'string', 'max:200'],
            'tasks.*.category'              => ['nullable', 'string', 'max:100'],
            'tasks.*.priority'              => ['nullable', 'string', Rule::in(EventTask::PRIORITIES)],
            'tasks.*.deadline'              => ['nullable', 'date'],
            'tasks.*.notes'                 => ['nullable', 'string', 'max:1000'],
            'tasks.*.slot_start_time'       => ['nullable', 'date_format:H:i'],
            'tasks.*.slot_end_time'         => ['nullable', 'date_format:H:i'],
            'tasks.*.slot_interval_minutes' => ['nullable', 'integer', Rule::in([15, 30, 45, 60, 90, 120])],
            'tasks.*.slot_capacity'         => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tasks.required'                      => 'At least one task must be selected for import.',
            'tasks.min'                           => 'At least one task must be selected for import.',
            'tasks.*.name.required'               => 'Each task must have a name.',
            'tasks.*.name.max'                    => 'Task name must not exceed 200 characters.',
            'tasks.*.priority.in'                 => 'Priority must be normal, important or critical.',
            'tasks.*.slot_start_time.date_format' => 'Slot start time must be in H:i format.',
            'tasks.*.slot_end_time.date_format'   => 'Slot end time must be in H:i format.',
            'tasks.*.slot_interval_minutes.in'    => 'Interval must be one of: 15, 30, 45, 60, 90, 120.',
            'tasks.*.slot_capacity.min'           => 'Capacity must be at least 1.',
        ];
    }
}
