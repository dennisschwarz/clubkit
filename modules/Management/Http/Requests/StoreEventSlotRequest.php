<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for assigning a member to an event task
 * with a specific time window (Einsatzplan tab modal).
 *
 * Moved from the Events module to Management where all task-related
 * controllers now live.
 *
 * time_from and time_to use H:i format (e.g. "10:00"). The controller
 * combines them with the event date to produce full datetime values.
 *
 * Business rules beyond basic validation (event-day task guard, duplicate
 * assignment prevention) are enforced in EventSlotController::store()
 * after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventSlotRequest extends FormRequest
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
            'event_task_id' => ['required', 'integer', 'exists:event_tasks,id'],
            'member_id'     => ['required', 'integer', 'exists:members,id'],
            'time_from'     => ['required', 'date_format:H:i'],
            'time_to'       => ['required', 'date_format:H:i', 'after:time_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_task_id.required' => __('events.slot.validation.task_required'),
            'event_task_id.exists'   => __('events.slot.validation.task_not_found'),
            'member_id.required'     => __('events.slot.validation.member_required'),
            'member_id.exists'       => __('events.slot.validation.member_not_found'),
            'time_from.required'     => __('events.slot.validation.time_from_required'),
            'time_from.date_format'  => __('events.slot.validation.time_from_format'),
            'time_to.required'       => __('events.slot.validation.time_to_required'),
            'time_to.date_format'    => __('events.slot.validation.time_to_format'),
            'time_to.after'          => __('events.slot.validation.time_to_after'),
        ];
    }
}