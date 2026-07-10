<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Einsatzplan slot configuration for an event task.
 *
 * These four fields define the time-grid for the task on the Einsatzplan tab:
 *
 *   slot_start_time         → H:i string, must be before slot_end_time
 *   slot_end_time           → H:i string
 *   slot_interval_minutes   → 30 | 60 | 90 | 120 (minutes per cell column)
 *   slot_capacity           → 1–20 persons per cell
 *
 * Business rules beyond validation (event-day task guard) are enforced
 * in EventSlotController::updateConfig() after this request passes.
 *
 * Permission is enforced at the route level (permission:events.manage).
 */
class UpdateSlotConfigRequest extends FormRequest
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
            'slot_start_time'       => ['required', 'date_format:H:i'],
            'slot_end_time'         => ['required', 'date_format:H:i', 'after:slot_start_time'],
            'slot_interval_minutes' => ['required', 'integer', 'in:15,30,45,60,90,120'],
            'slot_capacity'         => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slot_start_time.required'       => __('events.slot.validation.start_time_required'),
            'slot_start_time.date_format'    => __('events.slot.validation.start_time_format'),
            'slot_end_time.required'         => __('events.slot.validation.end_time_required'),
            'slot_end_time.date_format'      => __('events.slot.validation.end_time_format'),
            'slot_end_time.after'            => __('events.slot.validation.end_time_after'),
            'slot_interval_minutes.required' => __('events.slot.validation.interval_required'),
            'slot_interval_minutes.in'       => __('events.slot.validation.interval_invalid'),
            'slot_capacity.required'         => __('events.slot.validation.capacity_required'),
            'slot_capacity.min'              => __('events.slot.validation.capacity_min'),
            'slot_capacity.max'              => __('events.slot.validation.capacity_max'),
        ];
    }
}