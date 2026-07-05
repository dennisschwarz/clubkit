<?php

declare(strict_types=1);

namespace Modules\Management\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for assigning a member to an event task
 * without a time window (tasks tab inline assignment).
 *
 * Moved from the Events module to Management where all task-related
 * controllers now live.
 *
 * The event_task_id → event scope check (task belongs to this event)
 * and duplicate assignment prevention are enforced in
 * EventTaskMemberController::store() after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventTaskMemberRequest extends FormRequest
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'event_task_id.required' => 'Please select a task.',
            'event_task_id.exists'   => 'The selected task does not exist.',
            'member_id.required'     => 'Please select a member.',
            'member_id.exists'       => 'The selected member does not exist.',
        ];
    }
}
