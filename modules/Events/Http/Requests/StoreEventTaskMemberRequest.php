<?php

declare(strict_types=1);

namespace Modules\Events\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the request data for assigning a member to an event task
 * without a time slot (Aufgaben-Tab inline dropdown).
 *
 * Business rules beyond basic validation (task-event scope check,
 * duplicate assignment prevention) are enforced in
 * EventTaskMemberController::store() after this request passes.
 *
 * Permission is enforced at the route level via middleware('permission:events.manage').
 */
class StoreEventTaskMemberRequest extends FormRequest
{
    /**
     * @return bool
     */
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
            'task_id'   => ['required', 'integer', 'exists:management_tasks,id'],
            'member_id' => ['required', 'integer', 'exists:members,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'task_id.required'   => 'Bitte eine Aufgabe auswählen.',
            'task_id.exists'     => 'Die gewählte Aufgabe existiert nicht.',
            'member_id.required' => 'Bitte ein Mitglied auswählen.',
            'member_id.exists'   => 'Das gewählte Mitglied existiert nicht.',
        ];
    }
}
